/*
 * Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
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
import { oauthConnect, oauthConnectConfirmDialog } from './utils.js'

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

			if (DEBUG) console.debug('[Mattermost] before sendFileIdsAfterOAuth')
			this.sendFileIdsAfterOAuth(fileList)

			fileList.registerMultiSelectFileAction({
				name: 'mattermostSendMulti',
				displayName: (context) => {
					if (DEBUG) console.debug('[Mattermost] in registerMultiSelectFileAction->displayName: OCA.Mattermost.oauthPossible', OCA.Mattermost.oauthPossible)
					if (OCA.Mattermost.mattermostConnected || OCA.Mattermost.oauthPossible) {
						return t('integration_mattermost', 'Send files to Mattermost')
					}
					return ''
				},
				iconClass: () => {
					if (OCA.Mattermost.mattermostConnected || OCA.Mattermost.oauthPossible) {
						return 'icon-mattermost'
					}
				},
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
					}
				},
			})

			/*
			// when the multiselect menu is opened =>
			// only show 'send to mattermost' if at least one selected item is a file
			fileList.$el.find('.actions-selected').click(() => {
				if (OCA.Mattermost.mattermostConnected) {
					let showSendMultiple = false
					for (const fid in fileList._selectedFiles) {
						const file = fileList.files.find((t) => parseInt(fid) === t.id)
						if (file.type !== 'dir') {
							showSendMultiple = true
						}
					}
					fileList.fileMultiSelectMenu.toggleItemVisibility('mattermostSendMulti', showSendMultiple)
				}
			})
			*/

			fileList.fileActions.registerAction({
				name: 'mattermostSendSingle',
				displayName: (context) => {
					if (OCA.Mattermost.mattermostConnected || OCA.Mattermost.oauthPossible) {
						return t('integration_mattermost', 'Send to Mattermost')
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (OCA.Mattermost.mattermostConnected || OCA.Mattermost.oauthPossible) {
						return 'icon-mattermost'
					}
				},
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
					}
				},
			})
		},

		/**
		 * In case we successfully connected with oauth and got redirected back to files
		 * actually go on with the files that were previously selected
		 *
		 * @param {object} fileList the one from attach()
		 */
		sendFileIdsAfterOAuth: (fileList) => {
			const fileIdsStr = OCA.Mattermost.fileIdsToSendAfterOAuth
			if (DEBUG) console.debug('[Mattermost] in sendFileIdsAfterOAuth, fileIdsStr', fileIdsStr)
			// this is only true after an OAuth connection initated from a file action
			if (fileIdsStr) {
				const currentDir = OCA.Mattermost.currentDirAfterOAuth
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

function sendLinks(channelId, channelName, comment, permission, expirationDate, password) {
	const req = {
		fileIds: OCA.Mattermost.filesToSend.map((f) => f.id),
		channelId,
		channelName,
		comment,
		permission,
		expirationDate: expirationDate ? moment(expirationDate).format('YYYY-MM-DD') : undefined,
		password,
	}
	const url = generateUrl('apps/integration_mattermost/sendLinks')
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

function sendFileLoop(channelId, channelName, count = 0) {
	if (OCA.Mattermost.filesToSend.length === 0) {
		showSuccess(
			n(
				'integration_mattermost',
				'{count} file was sent to {channelName}',
				'{count} files were sent to {channelName}',
				count,
				{
					channelName,
					count,
				}
			)
		)
		OCA.Mattermost.MattermostSendModalVue.success()
		return
	}

	const file = OCA.Mattermost.filesToSend.shift()
	// skip directories
	if (file.type === 'dir') {
		sendFileLoop(channelId, channelName, count)
		return
	}
	OCA.Mattermost.MattermostSendModalVue.fileStarted(file.id)
	const req = {
		fileId: file.id,
		channelId,
	}
	const url = generateUrl('apps/integration_mattermost/sendFile')
	axios.post(url, req).then((response) => {
		// finished
		if (OCA.Mattermost.filesToSend.length === 0) {
			showSuccess(
				n(
					'integration_mattermost',
					'{fileName} was sent to {channelName}',
					'{count} files were sent to {channelName}',
					count + 1,
					{
						fileName: file.name,
						channelName,
						count: count + 1,
					}
				)
			)
			OCA.Mattermost.MattermostSendModalVue.success()
		} else {
			// not finished
			OCA.Mattermost.MattermostSendModalVue.fileFinished(file.id)
			sendFileLoop(channelId, channelName, count + 1)
		}
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		showError(
			t('integration_mattermost', 'Failed to send {name} to Mattermost', { name: file.name })
			+ ' ' + error.response?.request?.responseText
		)
	})
}

function sendMessage(channelId, message) {
	const req = {
		message,
		channelId,
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
	if (type === 'link') {
		sendLinks(channelId, channelName, comment, permission, expirationDate, password)
	} else {
		sendMessage(channelId, comment).then((response) => {
			sendFileLoop(channelId, channelName)
		}).catch((error) => {
			console.error(error)
			OCA.Mattermost.MattermostSendModalVue.failure()
			OCA.Mattermost.filesToSend = []
			showError(
				t('integration_mattermost', 'Failed to send files to Mattermost')
				+ ': ' + error.response?.request?.responseText
			)
		})
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
	OCA.Mattermost.fileIdsToSendAfterOAuth = response.data.file_ids_to_send_after_oauth
	OCA.Mattermost.currentDirAfterOAuth = response.data.current_dir_after_oauth
	if (DEBUG) console.debug('[Mattermost] OCA.Mattermost', OCA.Mattermost)
	OC.Plugins.register('OCA.Files.FileList', OCA.Mattermost.FilesPlugin)
}).catch((error) => {
	console.error(error)
})
