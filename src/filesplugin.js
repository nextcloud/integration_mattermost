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

const SEND_MESSAGE_URL = generateUrl('/apps/integration_slack/sendMessage')
const SEND_FILE_URL = generateUrl('/apps/integration_slack/sendFile')
const SEND_PUBLIC_LINKS_URL = generateUrl('/apps/integration_slack/sendPublicLinks')
const IS_CONNECTED_URL = generateUrl('/apps/integration_slack/is-connected')

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
				gotoSettingsConfirmDialog()
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
			if (DEBUG) console.debug('[Slack] in sendFileIdsAfterOAuth, fileIdsStr, currentDir', fileIdsStr, currentDir)

			// this is only true after an OAuth connection initated from a file action
			if (fileIdsStr) {
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

async function sendPublicLinks(channelId, channelName, comment, permission, expirationDate, password) {
	const req = {
		fileIds: OCA.Slack.filesToSend.map((f) => f.id),
		channelId,
		channelName,
		comment,
		permission,
		expirationDate: expirationDate ? moment(expirationDate).format('YYYY-MM-DD') : undefined,
		password,
	}

	return axios.post(SEND_PUBLIC_LINKS_URL, req)
}

const sendInternalLinks = async (channelId, comment) => {
	const getLink = (file) => window.location.protocol + '//' + window.location.host + generateUrl('/f/' + file.id)
	const message = (comment !== ''
		? `${comment}\n\n`
		: '') + `${OCA.Slack.filesToSend.map((file) => `${file.name}: ${getLink(file)}`).join('\n')}`
	return sendMessage(channelId, message)
}

const sendFile
	= (channelId, channelName, comment) => (file, i) => new Promise((resolve, reject) => {
		OCA.Slack.SlackSendModalVue.fileStarted(file.id)

		// send the comment only with the first file
		const req = {
			fileId: file.id,
			channelId,
			...(i === 0 && { comment }),
		}

		axios.post(SEND_FILE_URL, req).then((response) => {
			OCA.Slack.remoteFileIds.push(response.data.remote_file_id)
			OCA.Slack.sentFileNames.push(file.name)
			OCA.Slack.SlackSendModalVue.fileFinished(file.id)

			resolve()
		}).catch((error) => {
			showError(
				t('integration_slack', 'Failed to send {name} to {channelName} on Slack',
					{ name: file.name, channelName })
				+ ': ' + error.response?.request?.responseText
			)
			reject(error)
		})
	})

async function sendMessage(channelId, message) {
	const req = {
		message,
		channelId,
	}
	return axios.post(SEND_MESSAGE_URL, req)
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
	if (filesToSend.length === 0) {
		return
	}

	OCA.Slack.filesToSend = filesToSend

	if (type === SEND_TYPE.public_link.id) {
		sendPublicLinks(channelId, channelName, comment, permission, expirationDate, password).then(() => {
			showSuccess(
				n(
					'integration_slack',
					'A link to {fileName} was sent to {channelName}',
					'All of the {number} links were sent to {channelName}',
					OCA.Slack.filesToSend.length,
					{
						fileName: OCA.Slack.filesToSend[0].name,
						channelName,
						number: OCA.Slack.filesToSend.length,
					}
				)
			)
			OCA.Slack.SlackSendModalVue.success()
		}).catch((error) => {
			errorCallback(error)
			showError(
				t('integration_slack', 'Failed to send links to Slack')
				+ ' ' + error.response?.request?.responseText
			)
		})
	} else if (type === SEND_TYPE.internal_link.id) {
		sendInternalLinks(channelId, comment).then(() => {
			showSuccess(
				n(
					'integration_slack',
					'A link to {fileName} was sent to {channelName}',
					'All of the {number} links were sent to {channelName}',
					OCA.Slack.filesToSend.length,
					{
						fileName: OCA.Slack.filesToSend[0].name,
						number: OCA.Slack.filesToSend.length,
						channelName,
					}
				)
			)
			OCA.Slack.SlackSendModalVue.success()
		}).catch((error) => {
			errorCallback(error)
			showError(
				n(
					'integration_slack',
					'Failed to send the internal link to {channelName}',
					'Failed to send internal links to {channelName}',
					OCA.Slack.filesToSend.length,
					{
						fileName: OCA.Slack.filesToSend[0].name,
						channelName,
					}
				)
				+ ': ' + error.response?.request?.responseText
			)
		})
	} else {
		OCA.Slack.remoteFileIds = []
		OCA.Slack.sentFileNames = []
		OCA.Slack.filesToSend = filesToSend.filter((f) => f.type !== 'dir')

		Promise.all(OCA.Slack.filesToSend.map(sendFile(channelId, channelName, comment))).then(() => {
			showSuccess(
				n(
					'integration_slack',
					'{fileName} was successfully sent to {channelName}',
					'All of the {number} files were sent to {channelName}',
					OCA.Slack.filesToSend.length,
					{
						fileName: OCA.Slack.filesToSend[0].name,
						number: OCA.Slack.filesToSend.length,
						channelName,
					}
				)
			)
			OCA.Slack.SlackSendModalVue.success()
		}).catch(errorCallback)
	}
})

function errorCallback(error) {
	console.error(error)
	OCA.Slack.SlackSendModalVue.failure()
	OCA.Slack.filesToSend = []
	OCA.Slack.sentFileNames = []
}

// get Slack state
axios.get(IS_CONNECTED_URL).then((response) => {
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
