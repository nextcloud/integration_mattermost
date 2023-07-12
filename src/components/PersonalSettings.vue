<template>
	<div id="slack_prefs" class="section">
		<h2>
			<MattermostIcon class="icon" />
			{{ t('integration_slack', 'Slack integration') }}
		</h2>
		<p v-if="state.client_id === ''" class="settings-hint">
			{{ t('integration_slack', 'The admin must fill in client ID and client secret for you to continue from here') }}
		</p>
		<br>
		<div id="slack-content">
			<div id="slack-connect-block">
				<NcButton v-if="!connected"
					id="slack-connect"
					:disabled="loading === true || state.client_id === ''"
					:class="{ loading }"
					@click="connectWithOauth">
					<template #icon>
						<OpenInNewIcon />
					</template>
					{{ t('integration_slack', 'Connect to Slack') }}
				</NcButton>
				<div v-if="connected" class="line">
					<NcAvatar :url="getUserIconUrl()" :size="48" dispay-name="User" />
					<label class="slack-connected">
						{{ t('integration_slack', 'Connected as') }}
						{{ " " }}
						<b>{{ connectedDisplayName }}</b>
					</label>
					<NcButton id="slack-rm-cred" @click="onLogoutClick">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_slack', 'Disconnect from Slack') }}
					</NcButton>
				</div>
			</div>
			<br>
			<NcCheckboxRadioSwitch
				:checked.sync="state.file_action_enabled"
				@update:checked="onCheckboxChanged($event, 'file_action_enabled')">
				{{ t('integration_slack', 'Add file action to send files to Slack') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import MattermostIcon from './icons/MattermostIcon.vue'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { oauthConnect } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		MattermostIcon,
		NcAvatar,
		NcCheckboxRadioSwitch,
		NcButton,
		OpenInNewIcon,
		CloseIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_slack', 'user-config'),
			loading: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_slack/oauth-redirect'),
		}
	},

	computed: {
		connected() {
			return !!this.state.token && !!this.state.user_id
		},
		connectedDisplayName() {
			return this.state.user_displayname
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('result')
		if (glToken === 'success') {
			showSuccess(t('integration_slack', 'Successfully connected to Slack!'))
		} else if (glToken === 'error') {
			showError(t('integration_slack', 'Error connecting to Slack:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		getUserIconUrl() {
			return generateUrl(
				'/apps/integration_slack/users/{slackUserId}/image',
				{ slackUserId: this.state.user_id }
			) + '?useFallback=1'
		},
		onLogoutClick() {
			this.state.token = ''
			this.state.user_id = ''
			this.state.user_displayname = ''

			this.saveOptions({
				token: '',
				user_id: '',
				user_displayname: '',
			})
		},
		onCheckboxChanged(newValue, key) {
			this.saveOptions({ [key]: newValue ? '1' : '0' }, true)
		},
		saveOptions(values, checkboxChanged = false) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_slack/config')
			axios.put(url, req)
				.then((response) => {
					if (checkboxChanged) {
						showSuccess(t('integration_slack', 'Slack options saved'))
						return
					}

					if (response.data.user_id) {
						this.state.user_id = response.data.user_id
						if (!!this.state.token && !!this.state.user_id) {
							showSuccess(t('integration_slack', 'Successfully connected to Slack!'))
							this.state.user_id = response.data.user_id
							this.state.user_displayname = response.data.user_displayname
						} else {
							showError(t('integration_slack', 'Invalid access token'))
						}
					}
				})
				.catch((error) => {
					showError(
						t('integration_slack', 'Failed to save Slack options')
						+ ': ' + (error.response?.request?.responseText ?? '')
					)
					console.error(error)
				})
				.then(() => {
					this.loading = false
				})
		},
		connectWithOauth() {
			if (this.state.use_popup) {
				oauthConnect(this.state.client_id, null, true)
					.then((data) => {
						this.state.user_id = data.userId
						this.state.user_displayname = data.userDisplayName
					})
			} else {
				oauthConnect(this.state.client_id, 'settings')
			}
		},
	},
}
</script>

<style scoped lang="scss">
#slack_prefs {
	#slack-content {
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
		width: 450px;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
}
</style>
