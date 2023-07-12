import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import FileIcon from 'vue-material-design-icons/File.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

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
	const redirectUri = window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_slack/oauth-redirect')
	const oauthState = Math.random().toString(36).substring(3)
	const requestUrl = SLACK_OAUTH_URL
		+ '?client_id=' + encodeURIComponent(clientId)
		+ '&redirect_uri=' + encodeURIComponent(redirectUri)
		+ '&state=' + encodeURIComponent(oauthState)
		// TODO: refine this further
		+ '&user_scope=' + encodeURIComponent('channels:history,channels:read,chat:write,files:write,links:write,remote_files:share,users:read')

	const req = {
		values: {
			oauth_state: oauthState,
			redirect_uri: redirectUri,
			oauth_origin: usePopup ? undefined : oauthOrigin,
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
				+ ': ' + (error.response?.request?.responseText ?? '')
			)
			console.error(error)
		})
	})
}

export function oauthConnectConfirmDialog() {
	return new Promise((resolve) => {
		const settingsLink = generateUrl('/settings/user/connected-accounts')
		const linkText = t('integration_slack', 'Connected accounts')
		const settingsHtmlLink = `<a href="${settingsLink}" class="external">${linkText}</a>`
		OC.dialogs.message(
			t('integration_slack', 'You need to connect before using the Slack integration.')
			+ '<br><br>'
			+ t('integration_slack', 'You can set Slack API keys in the {settingsHtmlLink} section of your personal settings.',
				{ settingsHtmlLink },
				null,
				{ escape: false }),
			t('integration_slack', 'Connect to Slack'),
			'none',
			{
				type: OC.dialogs.YES_NO_BUTTONS,
				confirm: t('integration_slack', 'Connect'),
				confirmClasses: 'success',
				cancel: t('integration_slack', 'Cancel'),
			},
			(result) => {
				resolve(result)
			},
			true,
			true,
		)
	})
}

export function gotoSettingsConfirmDialog() {
	const settingsLink = generateUrl('/settings/user/connected-accounts')
	OC.dialogs.message(
		t('integration_slack', 'You need to connect a Slack app before using the Slack integration.')
		+ '<br><br>'
		+ t('integration_slack', 'Do you want to go to your "Connect accounts" personal settings?'),
		t('integration_slack', 'Connect to Slack'),
		'none',
		{
			type: OC.dialogs.YES_NO_BUTTONS,
			confirm: t('integration_slack', 'Go to settings'),
			confirmClasses: 'success',
			cancel: t('integration_slack', 'Cancel'),
		},
		(result) => {
			if (result) {
				window.location.replace(settingsLink)
			}
		},
		true,
		true,
	)
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
		icon: FileIcon,
	},
	public_link: {
		id: 'public_link',
		label: t('integration_slack', 'Public links'),
		icon: LinkVariantIcon,
	},
	internal_link: {
		id: 'internal_link',
		label: t('integration_slack', 'Internal links (Only works for users with access to the files)'),
		icon: OpenInNewIcon,
	},
}
