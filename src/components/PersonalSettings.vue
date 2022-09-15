<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<br>
		<div id="mattermost-content">
			<div id="mattermost-connect-block">
				<p v-if="!showOAuth && !connected" class="settings-hint">
					<InformationOutlineIcon :size="24" class="icon" />
					{{ t('integration_mattermost', 'If you are allowed to, You can create a personal access token in your Mattermost profile -> Security -> Personal Access Tokens.') }}
				</p>
				<p v-if="!showOAuth && !connected" class="settings-hint">
					{{ t('integration_mattermost', 'You can connect with a personal token OR just with your login/password.') }}
				</p>
				<div class="line">
					<label for="mattermost-url">
						<EarthIcon :size="20" class="icon" />
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
					class="line">
					<label for="mattermost-token">
						<KeyIcon :size="20" class="icon" />
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
					class="line">
					<label
						for="mattermost-login">
						<AccountIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Login') }}
					</label>
					<input id="mattermost-login"
						v-model="login"
						type="text"
						:placeholder="t('integration_mattermost', 'Mattermost login')"
						@keyup.enter="onConnectClick">
				</div>
				<div v-show="showLoginPassword"
					class="line">
					<label
						for="mattermost-password">
						<LockIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Password') }}
					</label>
					<input id="mattermost-password"
						v-model="password"
						type="password"
						:placeholder="t('integration_mattermost', 'Mattermost password')"
						@keyup.enter="onConnectClick">
				</div>
				<NcButton v-if="!connected"
					id="mattermost-connect"
					:disabled="loading === true || (!showOAuth && !state.token && !(login && password))"
					:class="{ loading }"
					@click="onConnectClick">
					<template #icon>
						<OpenInNewIcon />
					</template>
					{{ t('integration_mattermost', 'Connect to Mattermost') }}
				</NcButton>
				<div v-if="connected" class="line">
					<label class="mattermost-connected">
						<CheckIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Connected as {user}', { user: connectedDisplayName }) }}
					</label>
					<NcButton id="mattermost-rm-cred" @click="onLogoutClick">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_mattermost', 'Disconnect from Mattermost') }}
					</NcButton>
				</div>
			</div>
			<br>
			<CheckboxRadioSwitch
				:checked.sync="state.file_action_enabled"
				@update:checked="onCheckboxChanged($event, 'file_action_enabled')">
				{{ t('integration_mattermost', 'Add file action to send files to Mattermost') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch
				:checked.sync="state.navigation_enabled"
				@update:checked="onNavigationChange">
				{{ t('integration_mattermost', 'Enable navigation link (link to Mattermost with a top menu item)') }}
			</CheckboxRadioSwitch>
			<div v-if="connected" id="mattermost-search-block">
				<CheckboxRadioSwitch
					:checked.sync="state.search_messages_enabled"
					@update:checked="onSearchChange">
					{{ t('integration_mattermost', 'Enable searching for messages') }}
				</CheckboxRadioSwitch>
				<br>
				<p v-if="state.search_messages_enabled" class="settings-hint">
					<InformationOutlineIcon :size="24" class="icon" />
					{{ t('integration_mattermost', 'Warning, everything you type in the search bar will be sent to Mattermost.') }}
				</p>
			</div>
			<br>
			<div v-if="false && connected" id="mattermost-webhooks-block">
				<p class="settings-hint">
					<InformationOutlineIcon :size="24" class="icon" />
					{{ t('integration_mattermost', 'If you have configured the Nextcloud integration in Mattermost, it will automatically remotely configure those webhooks.') }}
					{{ t('integration_mattermost', 'This section does not require to be connected to Mattermost from Nextcloud.') }}
				</p>
				<CheckboxRadioSwitch
					:checked.sync="state.webhooks_enabled"
					@update:checked="onCheckboxChanged($event, 'webhooks_enabled')">
					{{ t('integration_mattermost', 'Enable webhooks') }}
				</CheckboxRadioSwitch>
				<div class="line">
					<label for="mattermost-cal-event-add">
						<WebhookIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Calendar event created webhook URL') }}
					</label>
					<input id="mattermost-cal-event-add"
						v-model="state.calendar_event_created_webhook"
						type="text"
						:disabled="!state.webhooks_enabled"
						:placeholder="t('integration_mattermost', 'https://my.mattermost.org/webhook...')"
						@input="onInput">
				</div>
				<div class="line">
					<label for="mattermost-cal-event-edit">
						<WebhookIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Calendar event updated webhook URL') }}
					</label>
					<input id="mattermost-cal-event-edit"
						v-model="state.calendar_event_updated_webhook"
						type="text"
						:disabled="!state.webhooks_enabled"
						:placeholder="t('integration_mattermost', 'https://my.mattermost.org/webhook...')"
						@input="onInput">
				</div>
				<div class="line">
					<label for="mattermost-webhook-secret">
						<KeyIcon :size="20" class="icon" />
						{{ t('integration_mattermost', 'Webhook secret') }}
					</label>
					<input id="mattermost-webhook-secret"
						v-model="state.webhook_secret"
						type="password"
						:disabled="!state.webhooks_enabled"
						:placeholder="t('integration_mattermost', 'secret')"
						@input="onInput">
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import LockIcon from 'vue-material-design-icons/Lock.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import WebhookIcon from 'vue-material-design-icons/Webhook.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, oauthConnect } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch.js'
import MattermostIcon from './icons/MattermostIcon.vue'

export default {
	name: 'PersonalSettings',

	components: {
		MattermostIcon,
		CheckboxRadioSwitch,
		NcButton,
		OpenInNewIcon,
		CloseIcon,
		InformationOutlineIcon,
		WebhookIcon,
		EarthIcon,
		CheckIcon,
		LockIcon,
		KeyIcon,
		AccountIcon,
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
			return !!this.state.token
				&& !!this.state.url
				&& !!this.state.user_name
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
			delay(() => {
				this.saveOptions({
					url: this.state.url,
					webhook_secret: this.state.webhook_secret,
					calendar_event_created_webhook: this.state.calendar_event_created_webhook,
					calendar_event_updated_webhook: this.state.calendar_event_updated_webhook,
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
	#mattermost-content {
		margin-left: 40px;
	}

	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
