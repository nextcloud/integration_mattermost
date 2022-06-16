<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a v-if="!initialState.oauth_is_possible"
							class="button"
							:href="settingsUrl">
							{{ t('integration_mattermost', 'Connect to Mattermost') }}
						</a>
						<Button v-else
							@click="onOauthClick">
							<template #icon>
								<LoginVariantIcon />
							</template>
							{{ t('integration_mattermost', 'Connect to Mattermost') }}
						</Button>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant'
import Button from '@nextcloud/vue/dist/Components/Button'

import { oauthConnect } from '../utils'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
		EmptyContent,
		Button,
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
			state: 'loading',
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
			if (this.state === 'no-token') {
				return t('integration_mattermost', 'No Mattermost account connected')
			} else if (this.state === 'error') {
				return t('integration_mattermost', 'Error connecting to Mattermost')
			} else if (this.state === 'ok') {
				return t('integration_mattermost', 'No Mattermost notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-mattermost'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
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
			oauthConnect(this.mattermostUrl, this.initialState.client_id, 'dashboard')
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
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_mattermost', 'Failed to get Mattermost notifications'))
					this.state = 'error'
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
			return this.mattermostUrl + '/' + n.team_name + '/channels/' + n.channel_name
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
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
