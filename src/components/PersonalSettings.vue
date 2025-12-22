<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<div id="mattermost-content">
			<div id="mattermost-connect-block">
				<NcNoteCard v-if="!showOAuth && !connected"
					type="info">
					{{ t('integration_mattermost', 'If you are allowed to, you can create a personal access token in your Mattermost profile -> Security -> Personal Access Tokens.') }}
					<br>
					{{ t('integration_mattermost', 'You can connect with a personal token OR just with your login/password.') }}
				</NcNoteCard>
				<NcTextField
					v-model="state.url"
					:label="t('integration_mattermost', 'Mattermost instance address')"
					:placeholder="t('integration_mattermost', 'Mattermost instance address')"
					:disabled="connected === true"
					:show-trailing-button="!!state.url"
					@trailing-button-click="state.url = ''; onSensitiveInput()"
					@update:model-value="onSensitiveInput">
					<template #icon>
						<EarthIcon :size="20" />
					</template>
				</NcTextField>
				<NcTextField v-show="showToken"
					v-model="state.token"
					type="password"
					:label="t('integration_mattermost', 'Personal access token')"
					:placeholder="t('integration_mattermost', 'Mattermost personal access token')"
					:disabled="connected === true"
					:show-trailing-button="!!state.token"
					@trailing-button-click="state.token = ''"
					@keyup.enter="onConnectClick">
					<template #icon>
						<KeyOutlineIcon :size="20" />
					</template>
				</NcTextField>
				<NcTextField v-show="showLoginPassword"
					v-model="login"
					:label="t('integration_mattermost', 'Login')"
					:placeholder="t('integration_mattermost', 'Mattermost login')"
					:show-trailing-button="!!login"
					@trailing-button-click="login = ''"
					@keyup.enter="onConnectClick">
					<template #icon>
						<AccountOutlineIcon :size="20" />
					</template>
				</NcTextField>
				<NcTextField v-show="showLoginPassword"
					v-model="password"
					type="password"
					:label="t('integration_mattermost', 'Password')"
					:placeholder="t('integration_mattermost', 'Mattermost password')"
					:show-trailing-button="!!password"
					@trailing-button-click="password = ''"
					@keyup.enter="onConnectClick">
					<template #icon>
						<LockOutlineIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton v-if="!connected"
					id="mattermost-connect"
					:disabled="loading === true || (!showOAuth && !state.token && !(login && password))"
					@click="onConnectClick">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<OpenInNewIcon v-else :size="20" />
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
							<CloseIcon :size="20" />
						</template>
						{{ t('integration_mattermost', 'Disconnect from Mattermost') }}
					</NcButton>
				</div>
			</div>
			<br>
			<NcFormBox>
				<NcFormBoxSwitch
					v-model="state.file_action_enabled"
					@update:model-value="onCheckboxChanged($event, 'file_action_enabled')">
					{{ t('integration_mattermost', 'Add file action to send files to Mattermost') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch
					v-model="state.navigation_enabled"
					@update:model-value="onNavigationChange">
					{{ t('integration_mattermost', 'Enable navigation link (link to Mattermost with a top menu item)') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="connected"
					v-model="state.search_messages_enabled"
					@update:model-value="onSearchChange">
					{{ t('integration_mattermost', 'Enable searching for messages') }}
				</NcFormBoxSwitch>
			</NcFormBox>
			<NcNoteCard v-if="connected && state.search_messages_enabled"
				type="info">
				{{ t('integration_mattermost', 'Warning, everything you type in the search bar will be sent to Mattermost.') }}
			</NcNoteCard>
			<br>
			<div id="mattermost-webhooks-block">
				<NcNoteCard type="info">
					{{ t('integration_mattermost', 'If you have configured the Nextcloud integration in Mattermost, it will automatically remotely configure those webhooks.') }}
					{{ t('integration_mattermost', 'This section does not require to be connected to Mattermost from Nextcloud.') }}
					<br>
					{{ t('integration_mattermost', 'NOTE: Webhooks feature has been disabled indefinitely until Mattermost implements it from their end.') }}
				</NcNoteCard>
				<NcFormBoxSwitch
					v-model="state.webhooks_enabled"
					disabled
					@update:model-value="onCheckboxChanged($event, 'webhooks_enabled')">
					{{ t('integration_mattermost', 'Enable webhooks') }}
				</NcFormBoxSwitch>
				<div v-if="state.webhooks_enabled" id="webhook-fields">
					<NcTextField
						v-model="state.calendar_event_created_webhook"
						:label="t('integration_mattermost', 'Calendar event created webhook URL')"
						:placeholder="'https://my.mattermost.org/webhook...'"
						:show-trailing-button="!!state.calendar_event_created_webhook"
						@trailing-button-click="state.calendar_event_created_webhook = ''; onInput()"
						@update:model-value="onInput">
						<template #icon>
							<WebhookIcon :size="20" />
						</template>
					</NcTextField>
					<NcTextField
						v-model="state.calendar_event_updated_webhook"
						:label="t('integration_mattermost', 'Calendar event updated webhook URL')"
						:placeholder="'https://my.mattermost.org/webhook...'"
						:show-trailing-button="!!state.calendar_event_updated_webhook"
						@trailing-button-click="state.calendar_event_updated_webhook = ''; onInput()"
						@update:model-value="onInput">
						<template #icon>
							<WebhookIcon :size="20" />
						</template>
					</NcTextField>
					<NcTextField
						v-model="state.daily_summary_webhook"
						:label="t('integration_mattermost', 'Daily summary webhook URL')"
						:placeholder="'https://my.mattermost.org/webhook...'"
						:show-trailing-button="!!state.daily_summary_webhook"
						@trailing-button-click="state.daily_summary_webhook = ''; onInput()"
						@update:model-value="onInput">
						<template #icon>
							<WebhookIcon :size="20" />
						</template>
					</NcTextField>
					<NcTextField
						v-model="state.imminent_events_webhook"
						:label="t('integration_mattermost', 'Upcoming events webhook URL')"
						:placeholder="'https://my.mattermost.org/webhook...'"
						:show-trailing-button="!!state.imminent_events_webhook"
						@trailing-button-click="state.imminent_events_webhook = ''; onInput()"
						@update:model-value="onInput">
						<template #icon>
							<WebhookIcon :size="20" />
						</template>
					</NcTextField>
					<NcTextField
						v-model="state.webhook_secret"
						type="password"
						:label="t('integration_mattermost', 'Webhook secret')"
						:placeholder="'https://my.mattermost.org/webhook...'"
						:show-trailing-button="!!state.webhook_secret"
						@trailing-button-click="state.webhook_secret = ''; onInput()"
						@update:model-value="onInput">
						<template #icon>
							<KeyOutlineIcon :size="20" />
						</template>
					</NcTextField>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import LockOutlineIcon from 'vue-material-design-icons/LockOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import WebhookIcon from 'vue-material-design-icons/Webhook.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import MattermostIcon from './icons/MattermostIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

import { delay, oauthConnect } from '../utils.js'

export default {
	name: 'PersonalSettings',

	components: {
		MattermostIcon,
		NcNoteCard,
		NcFormBox,
		NcFormBoxSwitch,
		NcButton,
		NcTextField,
		NcLoadingIcon,
		OpenInNewIcon,
		CloseIcon,
		WebhookIcon,
		EarthIcon,
		CheckIcon,
		LockOutlineIcon,
		KeyOutlineIcon,
		AccountOutlineIcon,
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
			this.saveOptions({ [key]: newValue ? '1' : '0' }, false)
		},
		onSearchChange(newValue) {
			this.saveOptions({ search_messages_enabled: newValue ? '1' : '0' }, false)
		},
		onNavigationChange(newValue) {
			this.saveOptions({ navigation_enabled: newValue ? '1' : '0' }, false)
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({
					webhook_secret: this.state.webhook_secret,
					calendar_event_created_webhook: this.state.calendar_event_created_webhook,
					calendar_event_updated_webhook: this.state.calendar_event_updated_webhook,
					daily_summary_webhook: this.state.daily_summary_webhook,
					imminent_events_webhook: this.state.imminent_events_webhook,
				}, false)
			}, 2000)()
		},
		onSensitiveInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({
					url: this.state.url,
				})
			}, 2000)()
		},
		async saveOptions(values, sensitive = true) {
			if (sensitive) {
				await confirmPassword()
			}
			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/integration_mattermost/sensitive-config')
				: generateUrl('/apps/integration_mattermost/config')
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
					showError(t('integration_mattermost', 'Failed to save Mattermost options'))
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
			// Do not overwrite the saved token if it is just the dummy token
			if (this.state.token !== 'dummyTokenContent') {
				this.loading = true
				this.saveOptions({
					token: this.state.token,
				})
			}
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
		justify-content: start;
		gap: 8px;
	}
	#mattermost-content {
		margin-left: 40px;
		display: flex;
		flex-direction: column;
		gap: 8px;
		max-width: 800px;

		#mattermost-connect {
			margin-top: 8px;
		}

		.line {
			display: flex;
			align-items: center;

			> label {
				width: 300px;
				display: flex;
				align-items: center;
			}
		}
	}
}
</style>
