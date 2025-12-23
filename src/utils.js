import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { shallowRef } from 'vue'
import FileOutlineIcon from 'vue-material-design-icons/FileOutline.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import SlackDialog from './components/SlackDialog.vue'

const SLACK_OAUTH_URL = 'https://slack.com/oauth/v2/authorize'

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function oauthConnect(clientId, oauthOrigin, usePopup = false) {
	const oauthState = Math.random().toString(36).substring(3)
	const redirectUri
		= window.location.protocol + '//' + window.location.host
		+ generateUrl('/apps/integration_slack/oauth-redirect')
	const userScopes = 'channels:read,groups:read,im:read,mpim:read,users:read,chat:write,files:write'

	const requestUrl = SLACK_OAUTH_URL
		+ '?client_id=' + encodeURIComponent(clientId)
		+ '&state=' + encodeURIComponent(oauthState)
		+ '&redirect_uri=' + encodeURIComponent(redirectUri)
		+ '&user_scope=' + encodeURIComponent(userScopes)

	const req = {
		values: {
			oauth_state: oauthState,
			oauth_origin: usePopup ? undefined : oauthOrigin,
			redirect_uri: redirectUri,
		},
	}

	const url = generateUrl('/apps/integration_slack/config')

	return new Promise((resolve) => {
		axios.put(url, req).then(() => {
			if (usePopup) {
				const ssoWindow = window.open(
					requestUrl,
					t('integration_slack', 'Sign in with Slack'),
					'toolbar=no, menubar=no, width=600, height=700')
				if (!ssoWindow) {
					showError(t('integration_slack', 'Failed to open Slack OAuth popup window, please allow popups'))
					return
				}
				ssoWindow.focus()

				window.addEventListener('message', (event) => {
					console.debug('Child window message received', event)
					resolve(event.data)
				})
			} else {
				window.location.href = requestUrl
			}
		}).catch((error) => {
			showError(
				t('integration_slack', 'Failed to save Slack OAuth state')
				+ ': ' + (error.response?.request?.responseText ?? ''),
			)
			console.error(error)
		})
	})
}

export async function oauthConnectConfirmDialog() {
	return await spawnDialog(SlackDialog, {
		title: t('integration_slack', 'Connect to Slack'),
		message: t('integration_slack', 'You need to connect before using the Slack integration.'),
		confirmText: t('integration_slack', 'Connect'),
	})
}

// seems like this function is unused
export async function gotoSettingsConfirmDialog() {
	const res = await spawnDialog(SlackDialog, {
		title: t('integration_slack', 'Connect to Slack'),
		message: t('integration_slack', 'You need to connect a Slack app before using the Slack integration.')
			+ ' '
			+ t('integration_slack', 'Do you want to go to your "Connect accounts" personal settings?'),
		confirmText: t('integration_slack', 'Go to settings'),
	})

	if (res) {
		window.location.href = generateUrl('/settings/user/connected-accounts')
	}
}

export function humanFileSize(bytes, approx = false, si = false, dp = 1) {
	const thresh = si ? 1000 : 1024

	if (Math.abs(bytes) < thresh) {
		return bytes + ' B'
	}

	const units = si
		? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
		: ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB']
	let u = -1
	const r = 10 ** dp

	do {
		bytes /= thresh
		++u
	} while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1)

	if (approx) {
		return Math.floor(bytes) + ' ' + units[u]
	} else {
		return bytes.toFixed(dp) + ' ' + units[u]
	}
}

export const SEND_TYPE = {
	file: {
		id: 'file',
		label: t('integration_slack', 'Upload files'),
		icon: shallowRef(FileOutlineIcon),
	},
	public_link: {
		id: 'public_link',
		label: t('integration_slack', 'Public links'),
		icon: shallowRef(LinkVariantIcon),
	},
	internal_link: {
		id: 'internal_link',
		label: t('integration_slack', 'Internal links (Only works for users with access to the files)'),
		icon: shallowRef(OpenInNewIcon),
	},
}
