/*
 * Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import SendFilesModal from './components/SendFilesModal'

import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { oauthConnect } from './utils'

import Vue from 'vue'
import './bootstrap'

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
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			this.sendFileIdsAfterOAuth(fileList)

			fileList.registerMultiSelectFileAction({
				name: 'mattermostSendMulti',
				displayName: (context) => {
					if (OCA.Mattermost.mattermostConnected) {
						return t('integration_mattermost', 'Send files to Mattermost')
					}
					return ''
				},
				iconClass: () => {
					if (OCA.Mattermost.mattermostConnected) {
						return 'icon-mattermost'
					}
				},
				order: -2,
				action: (selectedFiles) => { this.sendMulti(selectedFiles) },
			})

			fileList.registerMultiSelectFileAction({
				name: 'mattermostConnectMulti',
				displayName: (context) => {
					if (!OCA.Mattermost.mattermostConnected && OCA.Mattermost.oauthPossible) {
						return t('integration_mattermost', 'Connect to Mattermost')
					}
					return ''
				},
				iconClass: () => {
					if (!OCA.Mattermost.mattermostConnected && OCA.Mattermost.oauthPossible) {
						return 'icon-mattermost'
					}
				},
				order: -2,
				action: (selectedFiles) => { this.connectToMattermost(selectedFiles.map((f) => f.id)) },
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
					if (OCA.Mattermost.mattermostConnected) {
						return t('integration_mattermost', 'Send to Mattermost')
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (OCA.Mattermost.mattermostConnected) {
						return 'icon-mattermost'
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: (fileName, context) => { this.sendSingle(fileName, context) },
			})

			fileList.fileActions.registerAction({
				name: 'mattermostConnectSingle',
				displayName: (context) => {
					if (!OCA.Mattermost.mattermostConnected && OCA.Mattermost.oauthPossible) {
						return t('integration_mattermost', 'Connect to Mattermost')
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (!OCA.Mattermost.mattermostConnected && OCA.Mattermost.oauthPossible) {
						return 'icon-mattermost'
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: (fileName, context) => { this.connectToMattermost([context.fileInfoModel.attributes.id]) },
			})
		},

		sendMulti: (selectedFiles) => {
			const files = selectedFiles
				// .filter((f) => f.type !== 'dir')
				.map((f) => {
					return {
						id: f.id,
						name: f.name,
						type: f.type,
					}
				})
			openChannelSelector(files)
		},

		sendSingle: (fileName, context) => {
			const file = {
				id: context.fileInfoModel.attributes.id,
				name: context.fileInfoModel.attributes.name,
				type: context.fileInfoModel.attributes.type,
			}
			openChannelSelector([file])
		},

		/**
		 * In case we successfully connected with oauth and got redirected back to files
		 * actually go on with the files that were previously selected
		 * @param fileList
		 */
		sendFileIdsAfterOAuth: (fileList) => {
			const fileIdsStr = OCA.Mattermost.fileIdsToSendAfterOAuth
			// this is only true after an OAuth connection initated from a file action
			if (fileIdsStr) {
				const currentDir = OCA.Mattermost.currentDirAfterOAuth
				// trick to make sure the file list is loaded (didn't find an event or a good alternative)
				fileList.changeDirectory(currentDir).then(() => {
					const fileIds = fileIdsStr.split(',')
					const files = fileIds.map((fid) => {
						const f = fileList.files.find((e) => e.id === parseInt(fid))
						if (f) {
							return {
								id: f.id,
								name: f.name,
								type: f.type,
							}
						}
						return null
					}).filter((e) => e !== null)
					if (files.length) {
						openChannelSelector(files)
					}
				})
			}
		},

		connectToMattermost: (selectedFilesIds = []) => {
			oauthConnect(
				OCA.Mattermost.mattermostUrl,
				OCA.Mattermost.clientId,
				'files--' + OCA.Files.App.fileList._currentDirectory + '--' + selectedFilesIds.join(',')
			)
		},
	}

})()

function sendLinks(channelId, channelName, comment, permission, expirationDate) {
	const req = {
		fileIds: OCA.Mattermost.filesToSend.map((f) => f.id),
		channelId,
		channelName,
		comment,
		permission,
		expirationDate: expirationDate ? moment(expirationDate).format('YYYY-MM-DD') : undefined,
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
	console.debug('mattermost modal closed')
})
OCA.Mattermost.MattermostSendModalVue.$on('validate', (channelId, channelName, type, comment, permission, expirationDate) => {
	if (type === 'link') {
		sendLinks(channelId, channelName, comment, permission, expirationDate)
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
	OCA.Mattermost.clientId = response.data.client_id
	OCA.Mattermost.mattermostUrl = response.data.url
	OCA.Mattermost.fileIdsToSendAfterOAuth = response.data.file_ids_to_send_after_oauth
	OCA.Mattermost.currentDirAfterOAuth = response.data.current_dir_after_oauth
	OC.Plugins.register('OCA.Files.FileList', OCA.Mattermost.FilesPlugin)
}).catch((error) => {
	console.error(error)
})
