<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="mattermost-icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<CheckboxRadioSwitch
			class="field"
			:checked.sync="state.navigation_enabled"
			@update:checked="onNavigationChange">
			{{ t('integration_mattermost', 'Enable navigation link') }}
		</CheckboxRadioSwitch>
		<br>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_mattermost', 'If you are allowed to, You can create a personal access token in your Mattermost profile -> Security -> Personal Access Tokens') }}
		</p>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_mattermost', 'You can connect with a personal token OR just with your login/password') }}
		</p>
		<div id="mattermost-content">
			<div class="field">
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
			</div>
			<div v-show="showToken"
				class="field">
				<label for="mattermost-token">
					<a class="icon icon-category-auth" />
					{{ t('integration_mattermost', 'Personal access token') }}
				</label>
				<input id="mattermost-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_mattermost', 'Mattermost personal access token')"
					@keyup.enter="onConnectClick">
			</div>
			<div v-show="showLoginPassword"
				class="field">
				<label
					for="mattermost-login">
					<a class="icon icon-user" />
					{{ t('integration_mattermost', 'Login') }}
				</label>
				<input id="mattermost-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_mattermost', 'Mattermost login')"
					@keyup.enter="onConnectClick">
			</div>
			<div v-show="showLoginPassword"
				class="field">
				<label
					for="mattermost-password">
					<a class="icon icon-password" />
					{{ t('integration_mattermost', 'Password') }}
				</label>
				<input id="mattermost-password"
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
			<div v-if="connected" class="field">
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
			</div>
			<br>
			<div v-if="connected" id="mattermost-search-block">
				<CheckboxRadioSwitch
					:checked.sync="state.search_messages_enabled"
					@update:checked="onSearchChange">
					{{ t('integration_mattermost', 'Enable searching for messages') }}
				</CheckboxRadioSwitch>
				<br>
				<p v-if="state.search_messages_enabled" class="settings-hint">
					<InformationVariantIcon :size="24" class="icon" />
					{{ t('integration_mattermost', 'Warning, everything you type in the search bar will be sent to Mattermost.') }}
				</p>
			</div>
			<CheckboxRadioSwitch v-if="!state.calendar_event_created_webhook_set"
				class="field"
				:checked.sync="state.calendar_event_created_webhook"
				@update:checked="onCheckboxChanged($event, 'calendar_event_created_webhook')">
				{{ t('integration_mattermost', 'Enable "calendar event created" webhook') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch v-if="!state.calendar_event_updated_webhook_set"
				class="field"
				:checked.sync="state.calendar_event_updated_webhook"
				@update:checked="onCheckboxChanged($event, 'calendar_event_updated_webhook')">
				{{ t('integration_mattermost', 'Enable "calendar event updated" webhook') }}
			</CheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew'
import CloseIcon from 'vue-material-design-icons/Close'
import InformationVariantIcon from 'vue-material-design-icons/InformationVariant'
import Button from '@nextcloud/vue/dist/Components/Button'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, oauthConnect } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import MattermostIcon from './icons/MattermostIcon'

export default {
	name: 'PersonalSettings',

	components: {
		MattermostIcon,
		CheckboxRadioSwitch,
		Button,
		OpenInNewIcon,
		CloseIcon,
		InformationVariantIcon,
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
		onCheckboxChanged(newValue, key) {
			this.saveOptions({ [key]: newValue ? '1' : '0' })
		},
		onSearchChange(newValue) {
			this.saveOptions({ search_messages_enabled: newValue ? '1' : '0' })
		},
		onNavigationChange(newValue) {
			this.saveOptions({ navigation_enabled: newValue ? '1' : '0' })
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
			if (this.state.use_popup) {
				oauthConnect(this.state.url, this.state.client_id, null, true)
					.then((data) => {
						this.state.token = 'dummyToken'
						this.state.user_name = data.userName
						this.state.user_displayname = data.userDisplayName
					})
			} else {
				oauthConnect(this.state.url, this.state.client_id, 'settings')
			}
		},
	},
}
</script>

<style scoped lang="scss">
#mattermost_prefs {
	h2 {
		display: flex;

		.mattermost-icon {
			margin-right: 12px;
		}
	}

	.field {
		display: flex;
		align-items: center;

		input,
		label {
			width: 300px;
		}

		label {
			display: flex;
			align-items: center;
		}

		.icon {
			margin-right: 8px;
		}
	}

	.field,
	#mattermost-search-block {
		margin-left: 30px;
	}

	.settings-hint {
		display: flex;
		align-items: center;
	}
}
</style>
