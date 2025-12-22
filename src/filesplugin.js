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
import {
	registerFileAction, Permission, FileAction,
	davGetClient, davGetDefaultPropfind, davResultToNode, davRootPath,
} from '@nextcloud/files'
import { subscribe } from '@nextcloud/event-bus'
import MattermostIcon from '../img/app-dark.svg'

import { createApp } from 'vue'

const DEBUG = false

if (!OCA.Mattermost) {
	OCA.Mattermost = {
		actionIgnoreLists: [
			'trashbin',
			'files.public',
		],
		filesToSend: [],
		currentFileList: null,
	}
}

subscribe('files:list:updated', onFilesListUpdated)
function onFilesListUpdated({ view, folder, contents }) {
	OCA.Mattermost.currentFileList = { view, folder, contents }
}

function openChannelSelector(files) {
	OCA.Mattermost.filesToSend = files
	const modalVue = OCA.Mattermost.MattermostSendModalVue
	modalVue.updateChannels()
	modalVue.setFiles([...files])
	modalVue.showModal()
}

const sendAction = new FileAction({
	id: 'mattermostSend',
	displayName: (nodes) => {
		return nodes.length > 1
			? t('integration_mattermost', 'Send files to Mattermost')
			: t('integration_mattermost', 'Send file to Mattermost')
	},
	enabled(nodes, view) {
		return !OCA.Mattermost.actionIgnoreLists.includes(view.id)
			&& nodes.length > 0
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			// && nodes.every(({ type }) => type === FileType.File)
			// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => MattermostIcon,
	async exec(node) {
		sendSelectedNodes([node])
		return null
	},
	async execBatch(nodes) {
		sendSelectedNodes(nodes)
		return nodes.map(_ => null)
	},
})
registerFileAction(sendAction)

function sendSelectedNodes(nodes) {
	const formattedNodes = nodes.map((node) => {
		return {
			id: node.fileid,
			name: node.basename,
			type: node.type,
			size: node.size,
		}
	})
	if (OCA.Mattermost.mattermostConnected) {
		openChannelSelector(formattedNodes)
	} else if (OCA.Mattermost.oauthPossible) {
		connectToMattermost(formattedNodes)
	} else {
		gotoSettingsConfirmDialog()
	}
}

function checkIfFilesToSend() {
	const urlCheckConnection = generateUrl('/apps/integration_mattermost/files-to-send')
	axios.get(urlCheckConnection)
		.then((response) => {
			const fileIdsStr = response?.data?.file_ids_to_send_after_oauth
			const currentDir = response?.data?.current_dir_after_oauth
			if (fileIdsStr && currentDir) {
				sendFileIdsAfterOAuth(fileIdsStr, currentDir)
			} else {
				if (DEBUG) console.debug('[Mattermost] nothing to send')
			}
		})
		.catch((error) => {
			console.error(error)
		})
}

/**
 * In case we successfully connected with oauth and got redirected back to files
 * actually go on with the files that were previously selected
 *
 * @param {string} fileIdsStr list of files to send
 * @param {string} currentDir path to the current dir
 */
async function sendFileIdsAfterOAuth(fileIdsStr, currentDir) {
	if (DEBUG) console.debug('[Mattermost] in sendFileIdsAfterOAuth, fileIdsStr, currentDir', fileIdsStr, currentDir)
	// this is only true after an OAuth connection initated from a file action
	if (fileIdsStr) {
		// get files info
		const client = davGetClient()
		const results = await client.getDirectoryContents(`${davRootPath}${currentDir}`, {
			details: true,
			// Query all required properties for a Node
			data: davGetDefaultPropfind(),
		})
		const nodes = results.data.map((r) => davResultToNode(r))

		const fileIds = fileIdsStr.split(',')
		const files = fileIds.map((fid) => {
			const f = nodes.find((n) => n.fileid === parseInt(fid))
			if (f) {
				return {
					id: f.fileid,
					name: f.basename,
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
	}
}

function connectToMattermost(selectedFiles = []) {
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
				const currentDirectory = OCA.Mattermost.currentFileList?.folder?.attributes?.filename
				oauthConnect(
					OCA.Mattermost.mattermostUrl,
					OCA.Mattermost.clientId,
					'files--' + currentDirectory + '--' + selectedFilesIds.join(','),
				)
			}
		}
	})
}

// ///////////////// Network

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
				},
			),
		)
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		showError(
			t('integration_mattermost', 'Failed to send links to Mattermost')
			+ ' ' + error.response?.request?.responseText,
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
				},
			),
		)
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		OCA.Mattermost.filesToSend = []
		showError(
			t('integration_mattermost', 'Failed to send internal links to Mattermost')
			+ ': ' + error.response?.request?.responseText,
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
			+ ' ' + error.response?.request?.responseText,
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
				},
			),
		)
		OCA.Mattermost.MattermostSendModalVue.success()
	}).catch((error) => {
		console.error(error)
		OCA.Mattermost.MattermostSendModalVue.failure()
		showError(
			t('integration_mattermost', 'Failed to send files to Mattermost')
			+ ': ' + error.response?.request?.responseText,
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

// ////////////// Main

// send file modal
const modalId = 'mattermostSendModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

const app = createApp(SendFilesModal)
app.mixin({ methods: { t, n } })
OCA.Mattermost.MattermostSendModalVue = app.mount(modalElement)

modalElement.addEventListener('closed', () => {
	if (DEBUG) console.debug('[Mattermost] modal closed')
})

modalElement.addEventListener('validate', (data) => {
	const { filesToSend, channelId, channelName, type, comment, permission, expirationDate, password } = data.detail

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
	if (DEBUG) console.debug('[Mattermost] before checkIfFilesToSend')
	checkIfFilesToSend()
})
