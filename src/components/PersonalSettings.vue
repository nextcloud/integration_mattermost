<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<a class="icon icon-mattermost" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<div id="toggle-mattermost-navigation-link">
			<input
				id="mattermost-link"
				type="checkbox"
				class="checkbox"
				:checked="state.navigation_enabled"
				@input="onNavigationChange">
			<label for="mattermost-link">{{ t('integration_mattermost', 'Enable navigation link') }}</label>
		</div>
		<br><br>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_mattermost', 'If you are allowed to, You can create a personal access token in your Mattermost profile -> Security -> Personal Access Tokens') }}
		</p>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_mattermost', 'You can connect with a personal token OR just with your login/password') }}
		</p>
		<div id="mattermost-content">
			<div class="mattermost-grid-form">
				<label for="mattermost-url">
					<a class="icon icon-link" />
					{{ t('integration_mattermost', 'Mattermost instance address') }}
				</label>
				<input id="mattermost-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_mattermost', 'Mattermost instance address')"
					@input="onInput">
				<label v-show="showToken"
					for="mattermost-token">
					<a class="icon icon-category-auth" />
					{{ t('integration_mattermost', 'Personal access token') }}
				</label>
				<input v-show="showToken"
					id="mattermost-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_mattermost', 'Mattermost personal access token')"
					@keyup.enter="onConnectClick">
				<label v-show="showLoginPassword"
					for="mattermost-login">
					<a class="icon icon-user" />
					{{ t('integration_mattermost', 'Login') }}
				</label>
				<input v-show="showLoginPassword"
					id="mattermost-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_mattermost', 'Mattermost login')"
					@keyup.enter="onConnectClick">
				<label v-show="showLoginPassword"
					for="mattermost-password">
					<a class="icon icon-password" />
					{{ t('integration_mattermost', 'Password') }}
				</label>
				<input v-show="showLoginPassword"
					id="mattermost-password"
					v-model="password"
					type="password"
					:placeholder="t('integration_mattermost', 'Mattermost password')"
					@keyup.enter="onConnectClick">
			</div>
			<Button v-if="!connected && (showOAuth || (login && password) || state.token)"
				id="mattermost-connect"
				:disabled="loading === true"
				:class="{ loading }"
				@click="onConnectClick">
				<template #icon>
					<OpenInNewIcon />
				</template>
				{{ t('integration_mattermost', 'Connect to Mattermost') }}
			</Button>
			<div v-if="connected" class="mattermost-grid-form">
				<label class="mattermost-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_mattermost', 'Connected as {user}', { user: connectedDisplayName }) }}
				</label>
				<Button id="mattermost-rm-cred" @click="onLogoutClick">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_mattermost', 'Disconnect from Mattermost') }}
				</Button>
				<span />
			</div>
			<br>
			<div v-if="connected" id="mattermost-search-block">
				<input
					id="search-mattermost"
					type="checkbox"
					class="checkbox"
					:checked="state.search_messages_enabled"
					@input="onSearchChange">
				<label for="search-mattermost">{{ t('integration_mattermost', 'Enable searching for messages') }}</label>
				<br><br>
				<p v-if="state.search_messages_enabled" class="settings-hint">
					<span class="icon icon-details" />
					{{ t('integration_mattermost', 'Warning, everything you type in the search bar will be sent to Mattermost.') }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew'
import CloseIcon from 'vue-material-design-icons/Close'
import Button from '@nextcloud/vue/dist/Components/Button'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
		Button,
		OpenInNewIcon,
		CloseIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_mattermost', 'user-config'),
			loading: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_mattermost/oauth-redirect'),
			login: '',
			password: '',
		}
	},

	computed: {
		showOAuth() {
			return (this.state.url === this.state.oauth_instance_url) && this.state.client_id && this.state.client_secret
		},
		connected() {
			return this.state.token && this.state.token !== ''
				&& this.state.url && this.state.url !== ''
				&& this.state.user_name && this.state.user_name !== ''
		},
		connectedDisplayName() {
			return this.state.user_displayname + ' (' + this.state.user_name + ')'
		},
		showLoginPassword() {
			return !this.showOAuth && !this.connected && !this.state.token
		},
		showToken() {
			return !this.showOAuth && !this.login && !this.password
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('mattermostToken')
		if (glToken === 'success') {
			showSuccess(t('integration_mattermost', 'Successfully connected to Mattermost!'))
		} else if (glToken === 'error') {
			showError(t('integration_mattermost', 'Error connecting to Mattermost:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.login = ''
			this.password = ''
			this.saveOptions({ token: '' })
		},
		onSearchChange(e) {
			this.state.search_messages_enabled = e.target.checked
			this.saveOptions({ search_messages_enabled: this.state.search_messages_enabled ? '1' : '0' })
		},
		onNavigationChange(e) {
			this.state.navigation_enabled = e.target.checked
			this.saveOptions({ navigation_enabled: this.state.navigation_enabled ? '1' : '0' })
		},
		onInput() {
			this.loading = true
			if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
				if (this.state.url.startsWith('http://')) {
					this.state.url = this.state.url.replace('http://', 'https://')
				} else {
					this.state.url = 'https://' + this.state.url
				}
			}
			delay(() => {
				this.saveOptions({
					url: this.state.url,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_mattermost/config')
			axios.put(url, req)
				.then((response) => {
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.state.token && response.data.user_name === '') {
							showError(t('integration_mattermost', 'Invalid access token'))
							this.state.token = ''
						} else if (this.login && this.password && response.data.user_name === '') {
							showError(t('integration_mattermost', 'Invalid login/password'))
						} else if (response.data.user_name) {
							showSuccess(t('integration_mattermost', 'Successfully connected to Mattermost!'))
							this.state.user_id = response.data.user_id
							this.state.user_name = response.data.user_name
							this.state.user_displayname = response.data.user_displayname
							this.state.token = 'dumdum'
						}
					} else {
						showSuccess(t('integration_mattermost', 'Mattermost options saved'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_mattermost', 'Failed to save Mattermost options')
						+ ': ' + (error.response?.request?.responseText ?? '')
					)
					console.error(error)
				})
				.then(() => {
					this.loading = false
				})
		},
		onConnectClick() {
			if (this.showOAuth) {
				this.connectWithOauth()
			} else if (this.login && this.password) {
				this.connectWithCredentials()
			} else {
				this.connectWithToken()
			}
		},
		connectWithToken() {
			this.loading = true
			this.saveOptions({
				token: this.state.token,
			})
		},
		connectWithCredentials() {
			this.loading = true
			this.saveOptions({
				login: this.login,
				password: this.password,
				url: this.state.url,
			})
		},
		connectWithOauth() {
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = this.state.url + '/oauth/authorize'
				+ '?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&redirect_uri=' + encodeURIComponent(this.redirect_uri)
				+ '&response_type=code'
				+ '&state=' + encodeURIComponent(oauthState)
			// + '&scope=' + encodeURIComponent('read_user read_api read_repository')

			const req = {
				values: {
					oauth_state: oauthState,
					redirect_uri: this.redirect_uri,
					oauth_origin: 'settings',
				},
			}
			const url = generateUrl('/apps/integration_mattermost/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_mattermost', 'Failed to save Mattermost OAuth state')
						+ ': ' + (error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.mattermost-grid-form label {
	line-height: 38px;
}

.mattermost-grid-form input {
	width: 100%;
}

.mattermost-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
}

#mattermost_prefs .icon {
	display: inline-block;
	width: 32px;
}

#mattermost_prefs .grid-form .icon {
	margin-bottom: -3px;
}

.icon-mattermost {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
	filter: var(--background-invert-if-dark);
}

// for NC <= 24
body.theme--dark .icon-mattermost {
	background-image: url(./../../img/app.svg);
}

#mattermost-content {
	margin-left: 40px;
}

#mattermost-search-block .icon {
	width: 22px;
}

#toggle-mattermost-navigation-link {
	margin-left: 40px;
}
</style>
