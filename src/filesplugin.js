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
	OCA.Mattermost.filesToSend = files
	const modalVue = OCA.Mattermost.MattermostSendModalVue
	modalVue.updateChannels()
	modalVue.setFiles([...files])
	modalVue.showModal()
}

(function() {
	if (!OCA.Mattermost) {
		/**
		 * @namespace
		 */
		OCA.Mattermost = {
			filesToSend: [],
		}
	}

	/**
	 * @namespace
	 */
	OCA.Mattermost.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		attach(fileList) {
			if (DEBUG) console.debug('[Mattermost] begin of attach')
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			if (DEBUG) console.debug('[Mattermost] before checkIfFilesToSend')
			this.checkIfFilesToSend(fileList)

			fileList.registerMultiSelectFileAction({
				name: 'mattermostSendMulti',
				displayName: t('integration_mattermost', 'Send files to Mattermost'),
				iconClass: 'icon-mattermost',
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
					if (OCA.Mattermost.mattermostConnected) {
						openChannelSelector(filesToSend)
					} else if (OCA.Mattermost.oauthPossible) {
						this.connectToMattermost(filesToSend)
					} else {
						gotoSettingsConfirmDialog()
					}
				},
			})

			fileList.fileActions.registerAction({
				name: 'mattermostSendSingle',
				displayName: t('integration_mattermost', 'Send to Mattermost'),
				iconClass: 'icon-mattermost',
				mime: 'all',
				order: -139,
				permissions: OC.PERMISSION_READ,
				actionHandler: (fileName, context) => {
					const filesToSend = [
						{
							id: context.fileInfoModel.attributes.id,
							name: context.fileInfoModel.attributes.name,
							type: context.fileInfoModel.attributes.type,
							size: context.fileInfoModel.attributes.size,
						},
					]
					if (OCA.Mattermost.mattermostConnected) {
						openChannelSelector(filesToSend)
					} else if (OCA.Mattermost.oauthPossible) {
						this.connectToMattermost(filesToSend)
					} else {
						gotoSettingsConfirmDialog()
					}
				},
			})
		},

		checkIfFilesToSend(fileList) {
			const urlCheckConnection = generateUrl('/apps/integration_mattermost/files-to-send')
			axios.get(urlCheckConnection).then((response) => {
				const fileIdsStr = response.data.file_ids_to_send_after_oauth
				const currentDir = response.data.current_dir_after_oauth
				if (fileIdsStr && currentDir) {
					this.sendFileIdsAfterOAuth(fileList, fileIdsStr, currentDir)
				} else {
					if (DEBUG) console.debug('[Mattermost] nothing to send')
				}
			}).catch((error) => {
				console.error(error)
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
					if (DEBUG) console.debug('[Mattermost] in sendFileIdsAfterOAuth, after changeDirectory, files:', files)
					if (files.length) {
						if (DEBUG) console.debug('[Mattermost] in sendFileIdsAfterOAuth, after changeDirectory, call openChannelSelector')
						openChannelSelector(files)
					}
				})
			}
		},

		connectToMattermost: (selectedFiles = []) => {
			oauthConnectConfirmDialog(OCA.Mattermost.mattermostUrl).then((result) => {
				if (result) {
					if (OCA.Mattermost.usePopup) {
						oauthConnect(OCA.Mattermost.mattermostUrl, OCA.Mattermost.clientId, null, true)
							.then((data) => {
								OCA.Mattermost.mattermostConnected = true
								openChannelSelector(selectedFiles)
							})
					} else {
						const selectedFilesIds = selectedFiles.map(f => f.id)
						oauthConnect(
							OCA.Mattermost.mattermostUrl,
							OCA.Mattermost.clientId,
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
		fileIds: OCA.Mattermost.filesToSend.map((f) => f.id),
		channelId,
		channelName,
		comment,
		permission,
		expirationDate: expirationDate ? moment(expirationDate).format('YYYY-MM-DD') : undefined,
		password,
	}
	const url = generateUrl('apps/integration_mattermost/sendPublicLinks')
	axios.post(url, req).then((response) => {
		const number = OCA.Mattermost.filesToSend.length
		showSuccess(
			n(
				'integration_mattermost',
				'A link to {fileName} was sent to {channelName}',
				'{number} links were sent to {channelName}',
				number,
				{
					fileName: OCA.Mattermost.filesToSend[0].name,
					channelName,
					number,
				}
			)
		)
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		showError(
			t('integration_mattermost', 'Failed to send links to Mattermost')
			+ ' ' + error.response?.request?.responseText
		)
	})
}

function sendInternalLinks(channelId, channelName, comment) {
	sendMessage(channelId, comment).then((response) => {
		OCA.Mattermost.filesToSend.forEach(f => {
			const link = window.location.protocol + '//' + window.location.host + generateUrl('/f/' + f.id)
			const message = f.name + ': ' + link
			sendMessage(channelId, message)
		})
		const number = OCA.Mattermost.filesToSend.length
		showSuccess(
			n(
				'integration_mattermost',
				'A link to {fileName} was sent to {channelName}',
				'{number} links were sent to {channelName}',
				number,
				{
					fileName: OCA.Mattermost.filesToSend[0].name,
					channelName,
					number,
				}
			)
		)
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		showError(
			t('integration_mattermost', 'Failed to send internal links to Mattermost')
			+ ': ' + error.response?.request?.responseText
		)
	})
}

function sendFileLoop(channelId, channelName, comment) {
	const file = OCA.Mattermost.filesToSend.shift()
	// skip directories
	if (file.type === 'dir') {
		if (OCA.Mattermost.filesToSend.length === 0) {
			// we are done, no next file
			sendMessageAfterFilesUpload(channelId, channelName, comment)
		} else {
			// skip, go to next
			sendFileLoop(channelId, channelName, comment)
		}
		return
	}
	OCA.Mattermost.MattermostSendModalVue.fileStarted(file.id)
	const req = {
		fileId: file.id,
		channelId,
	}
	const url = generateUrl('apps/integration_mattermost/sendFile')
	axios.post(url, req).then((response) => {
		OCA.Mattermost.remoteFileIds.push(response.data.remote_file_id)
		OCA.Mattermost.sentFileNames.push(file.name)
		OCA.Mattermost.MattermostSendModalVue.fileFinished(file.id)
		if (OCA.Mattermost.filesToSend.length === 0) {
			// finished
			sendMessageAfterFilesUpload(channelId, channelName, comment)
		} else {
			// not finished
			sendFileLoop(channelId, channelName, comment)
		}
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		OCA.Mattermost.sentFileNames = []
		showError(
			t('integration_mattermost', 'Failed to send {name} to Mattermost', { name: file.name })
			+ ' ' + error.response?.request?.responseText
		)
	})
}

function sendMessageAfterFilesUpload(channelId, channelName, comment) {
	const count = OCA.Mattermost.sentFileNames.length
	const lastFileName = count === 0 ? t('integration_mattermost', 'Nothing') : OCA.Mattermost.sentFileNames[count - 1]
	sendMessage(channelId, comment, OCA.Mattermost.remoteFileIds).then((response) => {
		showSuccess(
			n(
				'integration_mattermost',
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
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		showError(
			t('integration_mattermost', 'Failed to send files to Mattermost')
			+ ': ' + error.response?.request?.responseText
		)
	}).then(() => {
		OCA.Mattermost.filesToSend = []
		OCA.Mattermost.remoteFileIds = []
		OCA.Mattermost.sentFileNames = []
	})
}

function sendMessage(channelId, message, remoteFileIds = undefined) {
	const req = {
		message,
		channelId,
		remoteFileIds,
	}
	const url = generateUrl('apps/integration_mattermost/sendMessage')
	return axios.post(url, req)
}

// send file modal
const modalId = 'mattermostSendModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

const View = Vue.extend(SendFilesModal)
OCA.Mattermost.MattermostSendModalVue = new View().$mount(modalElement)

OCA.Mattermost.MattermostSendModalVue.$on('closed', () => {
	if (DEBUG) console.debug('[Mattermost] modal closed')
})
OCA.Mattermost.MattermostSendModalVue.$on('validate', ({ filesToSend, channelId, channelName, type, comment, permission, expirationDate, password }) => {
	OCA.Mattermost.filesToSend = filesToSend
	if (type === SEND_TYPE.public_link.id) {
		sendPublicLinks(channelId, channelName, comment, permission, expirationDate, password)
	} else if (type === SEND_TYPE.internal_link.id) {
		sendInternalLinks(channelId, channelName, comment)
	} else {
		OCA.Mattermost.remoteFileIds = []
		OCA.Mattermost.sentFileNames = []
		sendFileLoop(channelId, channelName, comment)
	}
})

// get Mattermost state
const urlCheckConnection = generateUrl('/apps/integration_mattermost/is-connected')
axios.get(urlCheckConnection).then((response) => {
	OCA.Mattermost.mattermostConnected = response.data.connected
	OCA.Mattermost.oauthPossible = response.data.oauth_possible
	OCA.Mattermost.usePopup = response.data.use_popup
	OCA.Mattermost.clientId = response.data.client_id
	OCA.Mattermost.mattermostUrl = response.data.url
	if (DEBUG) console.debug('[Mattermost] OCA.Mattermost', OCA.Mattermost)
}).catch((error) => {
	console.error(error)
})

document.addEventListener('DOMContentLoaded', () => {
	if (DEBUG) console.debug('[Mattermost] before register files plugin')
	OC.Plugins.register('OCA.Files.FileList', OCA.Mattermost.FilesPlugin)
})
