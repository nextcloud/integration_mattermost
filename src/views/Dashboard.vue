<template>
	<NcDashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="widgetState === 'loading'">
		<template #empty-content>
			<NcEmptyContent
				v-if="emptyContentMessage"
				:title="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
				<template #action>
					<div v-if="widgetState === 'no-token' || widgetState === 'error'" class="connect-button">
						<a v-if="!initialState.oauth_is_possible"
							:href="settingsUrl">
							<NcButton>
								<template #icon>
									<LoginVariantIcon />
								</template>
								{{ t('integration_mattermost', 'Connect to Mattermost') }}
							</NcButton>
						</a>
						<NcButton v-else
							@click="onOauthClick">
							<template #icon>
								<LoginVariantIcon />
							</template>
							{{ t('integration_mattermost', 'Connect to Mattermost') }}
						</NcButton>
					</div>
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import MattermostIcon from '../components/icons/MattermostIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDashboardWidget from '@nextcloud/vue/dist/Components/NcDashboardWidget.js'

import { oauthConnect, oauthConnectConfirmDialog } from '../utils.js'

export default {
	name: 'Dashboard',

	components: {
		NcDashboardWidget,
		NcEmptyContent,
		NcButton,
		LoginVariantIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			loop: null,
			widgetState: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts#mattermost_prefs'),
			initialState: loadState('integration_mattermost', 'user-config'),
			windowVisibility: true,
		}
	},

	computed: {
		mattermostUrl() {
			return this.initialState?.url?.replace(/\/+$/, '')
		},
		showMoreUrl() {
			return this.mattermostUrl
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
					// avatarUsername: this.getRepositoryName(n),
					avatarIsNoUser: true,
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].create_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.widgetState === 'no-token') {
				return t('integration_mattermost', 'No Mattermost account connected')
			} else if (this.widgetState === 'error') {
				return t('integration_mattermost', 'Error connecting to Mattermost')
			} else if (this.widgetState === 'ok') {
				return t('integration_mattermost', 'No Mattermost notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.widgetState === 'no-token') {
				return MattermostIcon
			} else if (this.widgetState === 'error') {
				return CloseIcon
			} else if (this.widgetState === 'ok') {
				return CheckIcon
			}
			return CheckIcon
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		onOauthClick() {
			oauthConnectConfirmDialog(this.mattermostUrl).then((result) => {
				if (result) {
					if (this.initialState.use_popup) {
						oauthConnect(this.mattermostUrl, this.initialState.client_id, null, true)
							.then((data) => {
								this.stopLoop()
								this.launchLoop()
							})
					} else {
						oauthConnect(this.mattermostUrl, this.initialState.client_id, 'dashboard')
					}
				}
			})
		},
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		async launchLoop() {
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_mattermost/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.widgetState = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.widgetState = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_mattermost', 'Failed to get Mattermost notifications'))
					this.widgetState = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastDate < newNotifications[i].create_at) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications.filter((n) => {
				return true
			})
		},
		getNotificationTarget(n) {
			return this.mattermostUrl + '/' + n.team_name + '/pl/' + n.id
		},
		getUniqueKey(n) {
			return n.id + ':' + n.create_at
		},
		getNotificationImage(n) {
			return generateUrl('/apps/integration_mattermost/users/{userId}/image', { userId: n.user_id })
		},
		getRepositoryName(n) {
			return n.project.path
				? n.project.path
				: ''
		},
		getNotificationTypeImage(n) {
			return imagePath('integration_mattermost', 'mention.svg')
		},
		getSubline(n) {
			if (n.channel_type === 'D') {
				return t('integration_mattermost', '{name} in @{direct_username} at {date}', { name: n.user_name, direct_username: n.direct_message_user_name, date: this.getFormattedDate(n) })
			}
			return t('integration_mattermost', '{name} in #{channel} at {date}', { name: n.user_name, channel: n.channel_name, date: this.getFormattedDate(n) })
		},
		getTargetTitle(n) {
			return n.message
		},
		getFormattedDate(n) {
			return moment(n.create_at).format('LLL')
		},
		editTodo(id, action) {
			axios.put(generateUrl('/apps/integration_mattermost/todos/' + id + '/' + action)).then((response) => {
			}).catch((error) => {
				showError(t('integration_mattermost', 'Failed to edit Mattermost todo'))
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
:deep(.connect-button) {
	margin-top: 10px;
}
</style>
