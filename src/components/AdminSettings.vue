<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_mattermost', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Mattermost instance of your choice, create an application in your Mattermost settings and set the ID and secret here.') }}
		</p>
		<br>
		<p class="settings-hint">
			<InformationOutlineIcon :size="24" class="icon" />
			{{ t('integration_mattermost', 'Make sure you set the "Redirect URI" to') }}
		</p>
		<strong>{{ redirect_uri }}</strong>
		<br><br>
		<p class="settings-hint">
			{{ t('integration_mattermost', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Mattermost" button in their personal settings if they select the Mattermost instance defined here.') }}
		</p>
		<div id="mattermost-content">
			<div class="line">
				<label for="mattermost-oauth-instance">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_mattermost', 'OAuth app instance address') }}
				</label>
				<input id="mattermost-oauth-instance"
					v-model="state.oauth_instance_url"
					type="text"
					:placeholder="t('integration_mattermost', 'Instance address')"
					@input="onInput">
			</div>
			<div class="line">
				<label for="mattermost-client-id">
					<KeyOutlineIcon :size="20" class="icon" />
					{{ t('integration_mattermost', 'Application ID') }}
				</label>
				<input id="mattermost-client-id"
					v-model="state.client_id"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_mattermost', 'ID of your Mattermost application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
			<div class="line">
				<label for="mattermost-client-secret">
					<KeyOutlineIcon :size="20" class="icon" />
					{{ t('integration_mattermost', 'Application secret') }}
				</label>
				<input id="mattermost-client-secret"
					v-model="state.client_secret"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_mattermost', 'Client secret of your Mattermost application')"
					@focus="readonly = false"
					@input="onInput">
			</div>
			<NcCheckboxRadioSwitch
				:checked.sync="state.use_popup"
				@update:checked="onUsePopupChanged">
				{{ t('integration_mattermost', 'Use a popup to authenticate') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked.sync="state.navlink_default"
				@update:checked="onNavlinkDefaultChanged">
				{{ t('integration_mattermost', 'Enable navigation link as default for all users') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'

import MattermostIcon from './icons/MattermostIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		MattermostIcon,
		NcCheckboxRadioSwitch,
		InformationOutlineIcon,
		EarthIcon,
		KeyOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_mattermost', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_mattermost/oauth-redirect'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUsePopupChanged(newValue) {
			this.saveOptions({ use_popup: newValue ? '1' : '0' }, false)
		},
		onNavlinkDefaultChanged(newValue) {
			this.saveOptions({ navlink_default: newValue ? '1' : '0' }, false)
		},
		onInput() {
			delay(() => {
				const values = {
					client_id: this.state.client_id,
					oauth_instance_url: this.state.oauth_instance_url,
				}
				if (this.state.client_secret !== 'dummySecret') {
					values.client_secret = this.state.client_secret
				}
				this.saveOptions(values)
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
				? generateUrl('/apps/integration_mattermost/sensitive-admin-config')
				: generateUrl('/apps/integration_mattermost/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_mattermost', 'Mattermost admin options saved'))
			}).catch((error) => {
				showError(t('integration_mattermost', 'Failed to save Mattermost admin options'))
				console.error(error)
			})
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
