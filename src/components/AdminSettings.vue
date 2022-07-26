<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="mattermost-icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_mattermost', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Mattermost instance of your choice, create an application in your Mattermost settings and set the ID and secret here.') }}
		</p>
		<br>
		<p class="settings-hint">
			<InformationVariantIcon :size="24" class="icon" />
			{{ t('integration_mattermost', 'Make sure you set the "Redirect URI" to') }}
			&nbsp;<b> {{ redirect_uri }} </b>
		</p>
		<br>
		<p class="settings-hint">
			{{ t('integration_mattermost', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Mattermost" button in their personal settings if they select the Mattermost instance defined here.') }}
		</p>
		<div class="field">
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
		<div class="field">
			<label for="mattermost-client-id">
				<KeyIcon :size="20" class="icon" />
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
		<div class="field">
			<label for="mattermost-client-secret">
				<KeyIcon :size="20" class="icon" />
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
		<CheckboxRadioSwitch
			class="field"
			:checked.sync="state.use_popup"
			@update:checked="onUsePopupChanged">
			{{ t('integration_mattermost', 'Use a popup to authenticate') }}
		</CheckboxRadioSwitch>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import MattermostIcon from './icons/MattermostIcon'
import InformationVariantIcon from 'vue-material-design-icons/InformationVariant'
import EarthIcon from 'vue-material-design-icons/Earth'
import KeyIcon from 'vue-material-design-icons/Key'

export default {
	name: 'AdminSettings',

	components: {
		MattermostIcon,
		CheckboxRadioSwitch,
		InformationVariantIcon,
		EarthIcon,
		KeyIcon,
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
			this.saveOptions({ use_popup: newValue ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_mattermost/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_mattermost', 'Mattermost admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_mattermost', 'Failed to save Mattermost admin options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#mattermost_prefs {
	.field {
		display: flex;
		align-items: center;
		margin-left: 30px;

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

	.settings-hint {
		display: flex;
		align-items: center;
	}

	h2 {
		display: flex;
		.mattermost-icon {
			margin-right: 12px;
		}
	}
}
</style>
