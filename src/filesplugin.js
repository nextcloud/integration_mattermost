/*
 * Copyright (c) 2022 Julien Veyssier <julien-nc@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import SendFilesModal from './components/SendFilesModal.vue'

import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { oauthConnect, oauthConnectConfirmDialog, gotoSettingsConfirmDialog, SEND_TYPE } from './utils.js'

import Vue from 'vue'
import './bootstrap.js'

const DEBUG = false

function openChannelSelector(files) {
	OCA.Slack.filesToSend = files
	const modalVue = OCA.Slack.SlackSendModalVue
	modalVue.updateChannels()
	modalVue.setFiles([...files])
	modalVue.showModal()
}

(function() {
	if (!OCA.Slack) {
		/**
		 * @namespace
		 */
		OCA.Slack = {
			filesToSend: [],
		}
	}

	/**
	 * @namespace
	 */
	OCA.Slack.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		attach(fileList) {
			if (DEBUG) console.debug('[Slack] begin of attach')
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			if (DEBUG) console.debug('[Slack] before checkIfFilesToSend')
			this.checkIfFilesToSend(fileList)

			fileList.registerMultiSelectFileAction({
				name: 'slackSendMulti',
				displayName: t('integration_slack', 'Send files to Slack'),
				iconClass: 'icon-slack',
				order: -2,
				action: (selectedFiles) => {
					const filesToSend = selectedFiles.map((f) => {
						return {
							id: f.id,
							name: f.name,
							type: f.type,
							size: f.size,
						}
					})
					if (OCA.Slack.slackConnected) {
						openChannelSelector(filesToSend)
					} else if (OCA.Slack.oauthPossible) {
						this.connectToSlack(filesToSend)
					} else {
						gotoSettingsConfirmDialog()
					}
				},
			})

			fileList.fileActions.registerAction({
				name: 'slackSendSingle',
				displayName: t('integration_slack', 'Send to Slack'),
				iconClass: 'icon-slack',
				mime: 'all',
				order: -139,
				permissions: OC.PERMISSION_READ,
				actionHandler: (_, context) => {
					const filesToSend = [
						{
							id: context.fileInfoModel.attributes.id,
							name: context.fileInfoModel.attributes.name,
							type: context.fileInfoModel.attributes.type,
							size: context.fileInfoModel.attributes.size,
						},
					]
					if (OCA.Slack.slackConnected) {
						openChannelSelector(filesToSend)
					} else if (OCA.Slack.oauthPossible) {
						this.connectToSlack(filesToSend)
					} else {
						gotoSettingsConfirmDialog()
					}
				},
			})
		},

		checkIfFilesToSend(fileList) {
			const urlCheckConnection = generateUrl('/apps/integration_slack/files-to-send')
			axios.get(urlCheckConnection).then((response) => {
				const fileIdsStr = response.data.file_ids_to_send_after_oauth
				const currentDir = response.data.current_dir_after_oauth

				if (fileIdsStr && currentDir) {
					this.sendFileIdsAfterOAuth(fileList, fileIdsStr, currentDir)
				} else {
					if (DEBUG) console.debug('[Slack] nothing to send')
				}
			}).catch((error) => {
				console.error(error)
				// TODO: connectToSlack?
			})
		},

		/**
		 * In case we successfully connected with oauth and got redirected back to files
		 * actually go on with the files that were previously selected
		 *
		 * @param {object} fileList the one from attach()
		 * @param {string} fileIdsStr list of files to send
		 * @param {string} currentDir path to the current dir
		 */
		sendFileIdsAfterOAuth: (fileList, fileIdsStr, currentDir) => {
			if (DEBUG) console.debug('[Mattermost] in sendFileIdsAfterOAuth, fileIdsStr, currentDir', fileIdsStr, currentDir)

			// this is only true after an OAuth connection initated from a file action
			if (fileIdsStr) {
				// TODO: n2 loop?
				// trick to make sure the file list is loaded (didn't find an event or a good alternative)
				// force=true to make sure we get a promise
				fileList.changeDirectory(currentDir, true, true).then(() => {
					const fileIds = fileIdsStr.split(',')
					const files = fileIds.map((fid) => {
						const f = fileList.files.find((e) => e.id === parseInt(fid))
						if (f) {
							return {
								id: f.id,
								name: f.name,
								type: f.type,
								size: f.size,
							}
						}
						return null
					}).filter((e) => e !== null)

					if (DEBUG) console.debug('[Slack] in sendFileIdsAfterOAuth, after changeDirectory, files:', files)

					if (files.length) {
						if (DEBUG) console.debug('[Slack] in sendFileIdsAfterOAuth, after changeDirectory, call openChannelSelector')
						openChannelSelector(files)
					}
				})
			}
		},

		connectToSlack: (selectedFiles = []) => {
			oauthConnectConfirmDialog().then((result) => {
				if (result) {
					if (OCA.Slack.usePopup) {
						oauthConnect(OCA.Slack.clientId, null, true)
							.then(() => {
								OCA.Slack.slackConnected = true
								openChannelSelector(selectedFiles)
							})
					} else {
						const selectedFilesIds = selectedFiles.map(f => f.id)
						oauthConnect(
							OCA.Slack.clientId,
							'files--' + OCA.Files.App.fileList._currentDirectory + '--' + selectedFilesIds.join(',')
						)
					}
				}
			})
		},
	}

})()

function sendPublicLinks(channelId, channelName, comment, permission, expirationDate, password) {
	const req = {
		fileIds: OCA.Slack.filesToSend.map((f) => f.id),
		channelId,
		channelName,
		comment,
		permission,
		expirationDate: expirationDate ? moment(expirationDate).format('YYYY-MM-DD') : undefined,
		password,
	}
	const url = generateUrl('apps/integration_slack/sendPublicLinks')
	axios.post(url, req).then((response) => {
		const number = OCA.Slack.filesToSend.length
		showSuccess(
			n(
				'integration_slack',
				'A link to {fileName} was sent to {channelName}',
				'{number} links were sent to {channelName}',
				number,
				{
					fileName: OCA.Slack.filesToSend[0].name,
					channelName,
					number,
				}
			)
		)
		OCA.Slack.SlackSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Slack.SlackSendModalVue.failure()
		OCA.Slack.filesToSend = []
		showError(
			t('integration_slack', 'Failed to send links to Slack')
			+ ' ' + error.response?.request?.responseText
		)
	})
}

function sendInternalLinks(channelId, channelName, comment) {
	sendMessage(channelId, comment).then((response) => {
		OCA.Slack.filesToSend.forEach(f => {
			const link = window.location.protocol + '//' + window.location.host + generateUrl('/f/' + f.id)
			const message = f.name + ': ' + link
			sendMessage(channelId, message)
		})
		const number = OCA.Slack.filesToSend.length
		showSuccess(
			n(
				'integration_slack',
				'A link to {fileName} was sent to {channelName}',
				'{number} links were sent to {channelName}',
				number,
				{
					fileName: OCA.Slack.filesToSend[0].name,
					channelName,
					number,
				}
			)
		)
		OCA.Slack.SlackSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Slack.SlackSendModalVue.failure()
		OCA.Slack.filesToSend = []
		showError(
			t('integration_slack', 'Failed to send internal links to Slack')
			+ ': ' + error.response?.request?.responseText
		)
	})
}

function sendFileLoop(channelId, channelName, comment) {
	const file = OCA.Slack.filesToSend.shift()
	// skip directories
	if (file.type === 'dir') {
		if (OCA.Slack.filesToSend.length === 0) {
			// we are done, no next file
			sendMessageAfterFilesUpload(channelId, channelName, comment)
		} else {
			// skip, go to next
			sendFileLoop(channelId, channelName, comment)
		}
		return
	}

	OCA.Slack.SlackSendModalVue.fileStarted(file.id)

	const req = {
		fileId: file.id,
		channelId,
	}
	const url = generateUrl('apps/integration_slack/sendFile')

	axios.post(url, req).then((response) => {
		OCA.Slack.remoteFileIds.push(response.data.remote_file_id)
		OCA.Slack.sentFileNames.push(file.name)
		OCA.Slack.SlackSendModalVue.fileFinished(file.id)

		if (OCA.Slack.filesToSend.length === 0) {
			// finished
			sendMessageAfterFilesUpload(channelId, channelName, comment)
		} else {
			// not finished
			sendFileLoop(channelId, channelName, comment)
		}
	}).catch((error) => {
		console.error(error)

		OCA.Slack.SlackSendModalVue.failure()
		OCA.Slack.filesToSend = []
		OCA.Slack.sentFileNames = []

		showError(
			t('integration_slack', 'Failed to send {name} to Slack', { name: file.name })
			+ ' ' + error.response?.request?.responseText
		)
	})
}

function sendMessageAfterFilesUpload(channelId, channelName, comment) {
	const count = OCA.Slack.sentFileNames.length
	const lastFileName = count === 0 ? t('integration_slack', 'Nothing') : OCA.Slack.sentFileNames[count - 1]

	sendMessage(channelId, comment, OCA.Slack.remoteFileIds).then((response) => {
		showSuccess(
			n(
				'integration_slack',
				'{fileName} was sent to {channelName}',
				'{count} files were sent to {channelName}',
				count,
				{
					fileName: lastFileName,
					channelName,
					count,
				}
			)
		)

		OCA.Slack.SlackSendModalVue.success()
	}).catch((error) => {
		console.error(error)

		OCA.Slack.SlackSendModalVue.failure()
		showError(
			t('integration_slack', 'Failed to send files to Slack')
			+ ': ' + error.response?.request?.responseText
		)
	}).then(() => {
		OCA.Slack.filesToSend = []
		OCA.Slack.remoteFileIds = []
		OCA.Slack.sentFileNames = []
	})
}

function sendMessage(channelId, message, remoteFileIds = undefined) {
	const req = {
		message,
		channelId,
		remoteFileIds,
	}
	const url = generateUrl('apps/integration_slack/sendMessage')
	return axios.post(url, req)
}

// send file modal
const modalId = 'slackSendModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

const View = Vue.extend(SendFilesModal)
OCA.Slack.SlackSendModalVue = new View().$mount(modalElement)

OCA.Slack.SlackSendModalVue.$on('closed', () => {
	if (DEBUG) console.debug('[Slack] modal closed')
})
OCA.Slack.SlackSendModalVue.$on('validate', ({ filesToSend, channelId, channelName, type, comment, permission, expirationDate, password }) => {
	OCA.Slack.filesToSend = filesToSend
	if (type === SEND_TYPE.public_link.id) {
		sendPublicLinks(channelId, channelName, comment, permission, expirationDate, password)
	} else if (type === SEND_TYPE.internal_link.id) {
		sendInternalLinks(channelId, channelName, comment)
	} else {
		OCA.Slack.remoteFileIds = []
		OCA.Slack.sentFileNames = []
		sendFileLoop(channelId, channelName, comment)
	}
})

// get Slack state
const urlCheckConnection = generateUrl('/apps/integration_slack/is-connected')
axios.get(urlCheckConnection).then((response) => {
	OCA.Slack.slackConnected = response.data.connected
	OCA.Slack.oauthPossible = response.data.oauth_possible
	OCA.Slack.usePopup = response.data.use_popup
	OCA.Slack.clientId = response.data.client_id
	if (DEBUG) console.debug('[Slack] OCA.Slack', OCA.Slack)
}).catch((error) => {
	console.error(error)
})

document.addEventListener('DOMContentLoaded', () => {
	if (DEBUG) console.debug('[Slack] before register files plugin')
	OC.Plugins.register('OCA.Files.FileList', OCA.Slack.FilesPlugin)
})
