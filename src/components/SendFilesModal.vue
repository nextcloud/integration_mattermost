<template>
	<div class="mattermost-modal-container">
		<NcModal v-if="show"
			size="normal"
			:name="t('integration_mattermost', 'Send files or links to Mattermost')"
			@close="closeModal">
			<div class="mattermost-modal-content">
				<h2 class="modal-title">
					<MattermostIcon />
					<span>
						{{ sendType === SEND_TYPE.file.id
							? n('integration_mattermost', 'Send file to Mattermost', 'Send files to Mattermost', files.length)
							: n('integration_mattermost', 'Send link to Mattermost', 'Send links to Mattermost', files.length)
						}}
					</span>
				</h2>
				<span class="field-label">
					<FileOutlineIcon />
					<span>
						<strong>
							{{ t('integration_mattermost', 'Files') }}
						</strong>
					</span>
				</span>
				<div class="files">
					<div v-for="f in files"
						:key="f.id"
						class="file">
						<NcLoadingIcon v-if="fileStates[f.id] === STATES.IN_PROGRESS"
							:size="20" />
						<CheckCircleOutlineIcon v-else-if="fileStates[f.id] === STATES.FINISHED"
							class="check-icon"
							:size="24" />
						<img v-else
							:src="getFilePreviewUrl(f.id, f.type)"
							class="file-image">
						<span class="file-name">
							{{ f.name }}
						</span>
						<div class="spacer" />
						<span class="file-size">
							{{ myHumanFileSize(f.size, true) }}
						</span>
						<NcButton class="remove-file-button"
							@click="onRemoveFile(f.id)">
							<template #icon>
								<CloseIcon :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
				<span class="field-label">
					<PoundIcon />
					<span>
						<strong>
							{{ t('integration_mattermost', 'Channel') }}
						</strong>
					</span>
				</span>
				<NcSelect
					v-model="selectedChannel"
					class="channel-select"
					:options="sortedChannels"
					label="display_name"
					:append-to-body="false"
					:placeholder="t('integration_mattermost', 'Choose a channel')"
					input-id="mattermost-channel-select"
					:aria-label-combobox="t('integration_mattermost', 'Channel')"
					@search="query = $event">
					<template #option="option">
						<div class="select-option">
							<NcAvatar v-if="option.team_display_name"
								:size="34"
								:url="getTeamIconUrl(option.team_id)"
								display-name="#" />
							<NcAvatar v-else-if="option.direct_message_display_name"
								:size="34"
								:url="getUserIconUrl(option.direct_message_user_id)"
								display-name="U" />
							<NcHighlight v-if="option.team_display_name"
								:text="'[' + option.team_display_name + '] ' + option.display_name"
								:search="query"
								class="multiselect-name" />
							<NcHighlight v-else-if="option.direct_message_display_name"
								:text="option.direct_message_display_name"
								:search="query"
								class="multiselect-name" />
						</div>
					</template>
					<template #selected-option="option">
						<NcAvatar v-if="option.team_display_name"
							:size="24"
							:url="getTeamIconUrl(option.team_id)"
							display-name="#" />
						<NcAvatar v-else-if="option.direct_message_display_name"
							:size="24"
							:url="getUserIconUrl(option.direct_message_user_id)"
							display-name="U" />
						<span v-if="option.team_display_name"
							class="multiselect-name">
							{{ '[' + option.team_display_name + '] ' + option.display_name }}
						</span>
						<span v-else-if="option.direct_message_display_name"
							class="multiselect-name">
							{{ option.direct_message_display_name }}
						</span>
					</template>
				</NcSelect>
				<div class="advanced-options">
					<span class="field-label">
						<UploadBoxOutlineIcon />
						<span>
							<strong>
								{{ t('integration_mattermost', 'Type') }}
							</strong>
						</span>
					</span>
					<div>
						<NcCheckboxRadioSwitch v-for="(type, key) in SEND_TYPE"
							:key="key"
							v-model="sendType"
							:value="type.id"
							name="send_type_radio"
							type="radio">
							<div class="checkbox-label">
								<component :is="type.icon" :size="20" />
								<span class="option-title">
									{{ type.label }}
								</span>
							</div>
						</NcCheckboxRadioSwitch>
					</div>
					<RadioElementSet v-if="sendType === SEND_TYPE.public_link.id"
						name="perm_radio"
						:options="permissionOptions"
						:value="selectedPermission"
						class="radios"
						@update:value="selectedPermission = $event">
						<!--template #icon="{option}">
							{{ option.label }}
						</template-->
						<!--template-- #label="{option}">
							{{ option.label + 'lala' }}
						</template-->
					</RadioElementSet>
					<div v-show="sendType === SEND_TYPE.public_link.id"
						class="expiration-field">
						<NcCheckboxRadioSwitch
							v-model="expirationEnabled">
							{{ t('integration_mattermost', 'Set expiration date') }}
						</NcCheckboxRadioSwitch>
						<div class="spacer" />
						<NcDateTimePicker v-show="expirationEnabled"
							id="expiration-datepicker"
							v-model="expirationDate"
							:disabled-date="isDateDisabled"
							:placeholder="t('integration_mattermost', 'Expires on')"
							:clearable="true" />
					</div>
					<div v-show="sendType === SEND_TYPE.public_link.id"
						class="password-field">
						<NcCheckboxRadioSwitch
							v-model="passwordEnabled">
							{{ t('integration_mattermost', 'Set link password') }}
						</NcCheckboxRadioSwitch>
						<div class="spacer" />
						<input v-show="passwordEnabled"
							id="password-input"
							v-model="password"
							type="text"
							:placeholder="passwordPlaceholder">
					</div>
					<span class="field-label">
						<CommentOutlineIcon />
						<span>
							<strong>
								{{ t('integration_mattermost', 'Comment') }}
							</strong>
						</span>
					</span>
					<div class="input-wrapper">
						<input v-model="comment"
							type="text"
							:placeholder="commentPlaceholder">
					</div>
				</div>
				<span v-if="warnAboutSendingDirectories"
					class="warning-container">
					<AlertBoxOutlineIcon class="warning-icon" />
					<label>
						{{ t('integration_mattermost', 'Directories will be skipped, they can only be sent as links.') }}
					</label>
				</span>
				<div class="mattermost-footer">
					<div class="spacer" />
					<NcButton
						@click="closeModal">
						{{ t('integration_mattermost', 'Cancel') }}
					</NcButton>
					<NcButton variant="primary"
						:class="{ loading, okButton: true }"
						:disabled="!canValidate"
						@click="onSendClick">
						<template #icon>
							<SendOutlineIcon />
						</template>
						{{ sendType === SEND_TYPE.file.id
							? n('integration_mattermost', 'Send file', 'Send files', files.length)
							: n('integration_mattermost', 'Send link', 'Send links', files.length)
						}}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import AlertBoxOutlineIcon from 'vue-material-design-icons/AlertBoxOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CommentOutlineIcon from 'vue-material-design-icons/CommentOutline.vue'
import EyeOutlineIcon from 'vue-material-design-icons/EyeOutline.vue'
import FileOutlineIcon from 'vue-material-design-icons/FileOutline.vue'
import UploadBoxOutlineIcon from 'vue-material-design-icons/UploadBoxOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import PoundIcon from 'vue-material-design-icons/Pound.vue'
import SendOutlineIcon from 'vue-material-design-icons/SendOutline.vue'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { FileType } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { humanFileSize, SEND_TYPE } from '../utils.js'
import MattermostIcon from './icons/MattermostIcon.vue'
import RadioElementSet from './RadioElementSet.vue'

const STATES = {
	IN_PROGRESS: 1,
	FINISHED: 2,
}

export default {
	name: 'SendFilesModal',

	components: {
		MattermostIcon,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcHighlight,
		NcModal,
		RadioElementSet,
		NcLoadingIcon,
		NcButton,
		NcAvatar,
		SendOutlineIcon,
		PoundIcon,
		FileOutlineIcon,
		UploadBoxOutlineIcon,
		CommentOutlineIcon,
		CheckCircleOutlineIcon,
		AlertBoxOutlineIcon,
		CloseIcon,
	},

	props: [],

	data() {
		return {
			SEND_TYPE,
			show: false,
			loading: false,
			sendType: SEND_TYPE.file.id,
			comment: '',
			query: '',
			files: [],
			fileStates: {},
			channels: [],
			selectedChannel: null,
			selectedPermission: 'view',
			expirationEnabled: false,
			expirationDate: null,
			passwordEnabled: false,
			password: '',
			passwordPlaceholder: t('integration_mattermost', 'password'),
			STATES,
			commentPlaceholder: t('integration_mattermost', 'Message to send with the files'),
			permissionOptions: {
				view: { label: t('integration_mattermost', 'View only'), icon: EyeOutlineIcon },
				edit: { label: t('integration_mattermost', 'Edit'), icon: PencilOutlineIcon },
			},
		}
	},

	computed: {
		warnAboutSendingDirectories() {
			return this.sendType === SEND_TYPE.file.id && this.files.findIndex((f) => f.type === 'dir') !== -1
		},
		onlyDirectories() {
			return this.files.filter((f) => f.type !== 'dir').length === 0
		},
		canValidate() {
			return this.selectedChannel !== null
				&& (this.sendType !== SEND_TYPE.file.id || !this.onlyDirectories)
				&& this.files.length > 0
		},
		sortedChannels() {
			return this.channels.slice().sort((a, b) => {
				const lpa = a.last_post_at
				const lpb = b.last_post_at
				return lpa < lpb
					? 1
					: lpa > lpb
						? -1
						: 0
			})
		},
	},

	watch: {
	},

	mounted() {
		this.reset()
	},

	methods: {
		reset() {
			this.selectedChannel = null
			this.files = []
			this.fileStates = {}
			this.channels = []
			this.comment = ''
			this.sendType = SEND_TYPE.file.id
			this.selectedPermission = 'view'
			this.expirationEnabled = false
			this.expirationDate = null
			this.passwordEnabled = false
			this.password = null
		},
		showModal() {
			this.show = true
		},
		closeModal() {
			this.show = false
			this.$el.dispatchEvent(new CustomEvent('closed', { bubbles: true }))
			this.reset()
		},
		setFiles(files) {
			this.files = files
		},
		onSendClick() {
			this.loading = true
			const _data = {
				filesToSend: [...this.files],
				channelId: this.selectedChannel.id,
				channelName: this.selectedChannel.display_name,
				type: this.sendType,
				comment: this.comment,
				permission: this.selectedPermission,
				expirationDate: this.sendType === SEND_TYPE.public_link.id && this.expirationEnabled ? this.expirationDate : null,
				password: this.sendType === SEND_TYPE.public_link.id && this.passwordEnabled ? this.password : null,
			}
			this.$el.dispatchEvent(
				new CustomEvent('validate', {
					detail: _data,
					bubbles: true,
				}),
			)
		},
		success() {
			this.loading = false
			this.closeModal()
		},
		failure() {
			this.loading = false
		},
		updateChannels() {
			const url = generateUrl('apps/integration_mattermost/channels')
			axios.get(url).then((response) => {
				this.channels = response.data
				if (this.sortedChannels.length > 0) {
					this.selectedChannel = this.sortedChannels[0]
				}
			}).catch((error) => {
				showError(t('integration_mattermost', 'Failed to load Mattermost channels'))
				console.error(error)
				console.error(error.response?.data?.error)
			})
		},
		getFilePreviewUrl(fileId, fileType) {
			if (fileType === FileType.Folder) {
				return generateUrl('/apps/theming/img/core/filetypes/folder.svg')
			}
			return generateUrl('/apps/integration_mattermost/preview?id={fileId}&x=100&y=100', { fileId })
		},
		fileStarted(id) {
			this.$set(this.fileStates, id, STATES.IN_PROGRESS)
		},
		fileFinished(id) {
			this.$set(this.fileStates, id, STATES.FINISHED)
		},
		getTeamIconUrl(teamId) {
			return generateUrl('/apps/integration_mattermost/teams/{teamId}/image', { teamId })
		},
		getUserIconUrl(userId) {
			return generateUrl('/apps/integration_mattermost/users/{userId}/image', { userId })
		},
		isDateDisabled(d) {
			const now = new Date()
			return d <= now
		},
		myHumanFileSize(bytes, approx = false, si = false, dp = 1) {
			return humanFileSize(bytes, approx, si, dp)
		},
		onRemoveFile(fileId) {
			const index = this.files.findIndex((f) => f.id === fileId)
			this.files.splice(index, 1)
		},
		onChannelSelected(selected) {
			if (selected !== null) {
				this.selectedChannel = selected
			}
		},
	},
}
</script>

<style scoped lang="scss">
.mattermost-modal-content {
	//width: 100%;
	padding: 16px;
	display: flex;
	flex-direction: column;
	overflow-y: scroll;

	.select-option {
		display: flex;
		align-items: center;
	}

	> *:not(.mattermost-footer) {
		margin-bottom: 16px;
	}

	.field-label {
		display: flex;
		align-items: center;
		margin: 12px 0;
		span {
			margin-left: 8px;
		}
	}

	> *:not(.field-label):not(.advanced-options):not(.mattermost-footer):not(.warning-container),
	.advanced-options > *:not(.field-label) {
		margin-left: 10px;
	}

	.advanced-options {
		display: flex;
		flex-direction: column;
	}

	.expiration-field {
		margin-top: 8px;
	}

	.password-field,
	.expiration-field {
		display: flex;
		align-items: center;
		> *:first-child {
			margin-right: 20px;
		}
		#expiration-datepicker,
		#password-input {
			width: 250px;
			margin: 0;
		}
	}

	.modal-title {
		display: flex;
		justify-content: center;
		span {
			margin-left: 8px;
		}
	}

	input[type='text'] {
		width: 100%;
	}

	.files {
		display: flex;
		flex-direction: column;
		.file {
			display: flex;
			align-items: center;
			margin: 4px 0;
			height: 40px;

			> *:first-child {
				width: 32px;
			}

			img {
				height: auto;
			}

			.file-name {
				margin-left: 12px;
				text-overflow: ellipsis;
				overflow: hidden;
				white-space: nowrap;
			}

			.file-size {
				white-space: nowrap;
			}

			.check-icon {
				color: var(--color-success);
			}

			.remove-file-button {
				width: 32px !important;
				height: 32px;
				margin-left: 8px;
				min-width: 32px;
				min-height: 32px;
			}
		}
	}

	.radios {
		margin-top: 8px;
		width: 250px;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 16px 0 16px 0;
	}

	.multiselect-name {
		margin-left: 8px;
	}

	.checkbox-label {
		display: flex;
		align-items: center;
		gap: 4px;
	}
}

.spacer {
	flex-grow: 1;
}

.mattermost-footer {
	display: flex;
	> * {
		margin-left: 8px;
	}
}

.warning-container {
	display: flex;
	> label {
		margin-left: 8px;
	}
	.warning-icon {
		color: var(--color-warning);
	}
}
</style>
