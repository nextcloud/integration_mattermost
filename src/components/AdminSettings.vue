<template>
	<div id="mattermost_prefs" class="section">
		<h2>
			<a class="icon icon-mattermost" />
			{{ t('integration_mattermost', 'Mattermost integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_mattermost', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Mattermost instance of your choice, create an application in your Mattermost settings and set the ID and secret here.') }}
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_mattermost', 'Make sure you set the "Redirect URI" to') }}
			<b> {{ redirect_uri }} </b>
			<br><br>
			{{ t('integration_mattermost', 'and give "read_user", "read_api" and "read_repository" permissions to the application. Optionally "api" instead.') }}
			<br>
			{{ t('integration_mattermost', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Mattermost" button in their personal settings if they select the Mattermost instance defined here.') }}
		</p>
		<div class="grid-form">
			<label for="mattermost-oauth-instance">
				<a class="icon icon-link" />
				{{ t('integration_mattermost', 'OAuth app instance address') }}
			</label>
			<input id="mattermost-oauth-instance"
				v-model="state.oauth_instance_url"
				type="text"
				:placeholder="t('integration_mattermost', 'Instance address')"
				@input="onInput">
			<label for="mattermost-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_mattermost', 'Application ID') }}
			</label>
			<input id="mattermost-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_mattermost', 'ID of your Mattermost application')"
				@input="onInput"
				@focus="readonly = false">
			<label for="mattermost-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_mattermost', 'Application secret') }}
			</label>
			<input id="mattermost-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_mattermost', 'Client secret of your Mattermost application')"
				@focus="readonly = false"
				@input="onInput">
			<CheckboxRadioSwitch
				:checked.sync="state.use_popup"
				@update:checked="onUsePopupChanged">
				{{ t('integration_google', 'Use a popup to authenticate') }}
			</CheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'AdminSettings',

	components: {
		CheckboxRadioSwitch,
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
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
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
</style>
