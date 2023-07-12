import { loadState } from '@nextcloud/initial-state'

const state = loadState('integration_slack', 'popup-data')
const userId = state.user_id
const userDisplayName = state.user_displayname

if (window.opener) {
	window.opener.postMessage({ userId, userDisplayName })
	window.close()
}
