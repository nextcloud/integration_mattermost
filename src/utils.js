import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

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

export function oauthConnect(mattermostUrl, clientId, oauthOrigin) {
	const redirectUri = window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_mattermost/oauth-redirect')

	const oauthState = Math.random().toString(36).substring(3)
	const requestUrl = mattermostUrl + '/oauth/authorize'
		+ '?client_id=' + encodeURIComponent(clientId)
		+ '&redirect_uri=' + encodeURIComponent(redirectUri)
		+ '&response_type=code'
		+ '&state=' + encodeURIComponent(oauthState)
	// + '&scope=' + encodeURIComponent('read_user read_api read_repository')

	const req = {
		values: {
			oauth_state: oauthState,
			redirect_uri: redirectUri,
			oauth_origin: oauthOrigin,
		},
	}
	const url = generateUrl('/apps/integration_mattermost/config')
	axios.put(url, req).then((response) => {
		window.location.replace(requestUrl)
	}).catch((error) => {
		showError(
			t('integration_mattermost', 'Failed to save Mattermost OAuth state')
			+ ': ' + (error.response?.request?.responseText ?? '')
		)
		console.error(error)
	})
}

export function oauthConnectConfirmDialog(mattermostUrl) {
	return new Promise((resolve, reject) => {
		const settingsLink = generateUrl('/settings/user/connected-accounts')
		const linkText = t('integration_mattermost', 'Connected accounts')
		const settingsHtmlLink = `<a href="${settingsLink}" class="external">${linkText}</a>`
		OC.dialogs.message(
			t('integration_mattermost', 'Are you sure you want to connect to {mmUrl}?', { mmUrl: mattermostUrl })
			+ '<br><br>'
			+ t(
				'integration_mattermost',
				'You can choose another Mattermost server in the {settingsHtmlLink} section of your personal settings.',
				{ settingsHtmlLink },
				null,
				{ escape: false }
			),
			t('integration_mattermost', 'Connect to Mattermost'),
			'none',
			{
				type: OC.dialogs.YES_NO_BUTTONS,
				confirm: t('integration_mattermost', 'Connect'),
				confirmClasses: 'success',
				cancel: t('integration_mattermost', 'Cancel'),
			},
			(result) => {
				resolve(result)
			},
			true,
			true,
		)
	})
}
