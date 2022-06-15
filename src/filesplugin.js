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
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

import Vue from 'vue'
import './bootstrap'

(function() {
	if (!OCA.Mattermost) {
		/**
		 * @namespace
		 */
		OCA.Mattermost = {
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
				action: (selectedFiles) => { this.sendMulti(selectedFiles, fileList, this) },
			})

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

			fileList.fileActions.registerAction({
				name: 'mattermostSendSingle',
				displayName: (context) => {
					if (OCA.Mattermost.mattermostConnected && context.fileInfoModel.attributes.type !== 'dir') {
						return t('integration_mattermost', 'Send files to Mattermost')
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (OCA.Mattermost.mattermostConnected && context.fileInfoModel.attributes.type !== 'dir') {
						return 'icon-mattermost'
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: (fileName, context) => { this.sendSingle(fileName, context, this) },
			})
		},

		sendMulti: (selectedFiles, fileList, that) => {
			const files = selectedFiles.map((f) => {
				return {
					id: f.id,
					name: f.name,
				}
			})
			console.debug('these are the selected files', files)
			const modalVue = OCA.Mattermost.MattermostSendModalVue
			modalVue.updateChannels()
			modalVue.setFiles(files)
			modalVue.showModal()
		},

		sendSingle: (fileName, context, that) => {
			const file = {
				id: context.fileInfoModel.attributes.id,
				name: context.fileInfoModel.attributes.name,
			}
			const modalVue = OCA.Mattermost.MattermostSendModalVue
			modalVue.updateChannels()
			modalVue.setFiles([file])
			modalVue.showModal()
		},
	}

})()

function send(files, channelId, channelName) {
	const fileIds = files.map((f) => f.id)
	const req = {
		fileIds,
		channelId,
	}
	const url = generateUrl('apps/integration_mattermost/send')
	axios.post(url, req).then((response) => {
		if (files.length > 1) {
			showSuccess(t('integration_mattermost', '{number} files were sent to {channelName}', { number: files.length, channelName }))
		}
		showSuccess(t('integration_mattermost', '{fileName} was sent to {channelName}', { fileName: files[0].name, channelName }))
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		showError(
			t('integration_mattermost', 'Failed to send files to Mattermost')
			+ ' ' + error.response?.request?.responseText
		)
	})
}

OC.Plugins.register('OCA.Files.FileList', OCA.Mattermost.FilesPlugin)

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
OCA.Mattermost.MattermostSendModalVue.$on('validate', (files, channelId, channelName) => {
	send(files, channelId, channelName)
})

// is Mattermost integration configured/connected?
const urlCheckConnection = generateUrl('/apps/integration_mattermost/is-connected')
axios.get(urlCheckConnection).then((response) => {
	OCA.Mattermost.mattermostConnected = response.data.connected
}).catch((error) => {
	console.error(error)
})
