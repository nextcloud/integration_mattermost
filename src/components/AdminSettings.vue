<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<MattermostIcon class="icon" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<div id="mattermost-content">
			<NcNoteCard type="info">
				{{ t('integration_mattermost', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Mattermost instance of your choice, create an application in your Mattermost settings and set the ID and secret here.') }}
				{{ t('integration_mattermost', 'Make sure you set the "Redirect URI" to') }}
				<br>
				<strong>{{ redirect_uri }}</strong>
				<br>
				{{ t('integration_mattermost', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Mattermost" button in their personal settings if they select the Mattermost instance defined here.') }}
			</NcNoteCard>
			<NcTextField
				v-model="state.oauth_instance_url"
				:label="t('integration_mattermost', 'OAuth app instance address')"
				:placeholder="t('integration_mattermost', 'Instance address')"
				:show-trailing-button="!!state.oauth_instance_url"
				@trailing-button-click="state.oauth_instance_url = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<EarthIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField
				v-model="state.client_id"
				type="password"
				:label="t('integration_mattermost', 'Application ID')"
				:placeholder="t('integration_mattermost', 'ID of your Mattermost application')"
				:readonly="readonly"
				:show-trailing-button="!!state.client_id"
				@trailing-button-click="state.client_id = ''; onInput()"
				@focus="readonly = false"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField
				v-model="state.client_secret"
				type="password"
				:label="t('integration_mattermost', 'Application secret')"
				:placeholder="t('integration_mattermost', 'Application secret of your Mattermost application')"
				:readonly="readonly"
				:show-trailing-button="!!state.client_secret"
				@trailing-button-click="state.client_secret = ''; onInput()"
				@focus="readonly = false"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcFormBox>
				<NcFormBoxSwitch
					v-model="state.use_popup"
					@update:model-value="onUsePopupChanged">
					{{ t('integration_mattermost', 'Use a popup to authenticate') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch
					v-model="state.navlink_default"
					@update:model-value="onNavlinkDefaultChanged">
					{{ t('integration_mattermost', 'Enable navigation link as default for all users') }}
				</NcFormBoxSwitch>
			</NcFormBox>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'

import MattermostIcon from './icons/MattermostIcon.vue'

import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

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
		NcNoteCard,
		NcTextField,
		NcFormBox,
		NcFormBoxSwitch,
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
		display: flex;
		flex-direction: column;
		gap: 8px;
		max-width: 800px;
	}

	h2 {
		display: flex;
		justify-content: start;
		gap: 8px;
	}
}
</style>
