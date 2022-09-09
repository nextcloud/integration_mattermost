<template>
	<div class="mattermost-modal-container">
		<Modal v-if="show"
			size="normal"
			@close="closeModal">
			<div class="mattermost-modal-content">
				<h2 class="modal-title">
					<MattermostIcon />
					<span>
						{{ sendType === 'file'
							? n('integration_mattermost', 'Send file to Mattermost', 'Send files to Mattermost', files.length)
							: n('integration_mattermost', 'Send link to Mattermost', 'Send links to Mattermost', files.length)
						}}
					</span>
				</h2>
				<span class="field-label">
					<FileIcon />
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
						<span v-if="fileStates[f.id] === STATES.IN_PROGRESS"
							class="icon-loading-small" />
						<!--LoadingIcon v-if="fileStates[f.id] === STATES.IN_PROGRESS"
							:size="32" /-->
						<CheckCircleIcon v-else-if="fileStates[f.id] === STATES.FINISHED"
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
					<PoundBoxIcon />
					<span>
						<strong>
							{{ t('integration_mattermost', 'Channel') }}
						</strong>
					</span>
				</span>
				<Multiselect
					v-model="selectedChannel"
					:placeholder="t('integration_mattermost', 'Choose a channel')"
					:options="channels"
					:user-select="true"
					label="display_name"
					track-by="id"
					:internal-search="true"
					class="channel-select"
					@search-change="query = $event">
					<template #option="{option}">
						<Avatar
							:size="34"
							:url="getTeamIconUrl(option.team_id)"
							display-name="#" />
						<Highlight
							:text="t('integration_mattermost', '[{teamName}] {channelName}', { channelName: option.display_name, teamName: option.team_display_name })"
							:search="query"
							class="multiselect-name" />
					</template>
					<template #singleLabel="{option}">
						<Avatar
							:size="34"
							:url="getTeamIconUrl(option.team_id)"
							display-name="#" />
						<span class="multiselect-name">
							{{ t('integration_mattermost', '[{teamName}] {channelName}', { channelName: option.display_name, teamName: option.team_display_name }) }}
						</span>
					</template>
					<template #noOptions>
						{{ t('integration_mattermost', 'Start typing to search') }}
					</template>
				</Multiselect>
				<div class="advanced-options">
					<span class="field-label">
						<PackageUpIcon />
						<span>
							<strong>
								{{ t('integration_mattermost', 'Type') }}
							</strong>
						</span>
					</span>
					<div>
						<CheckboxRadioSwitch
							:checked.sync="sendType"
							value="file"
							name="send_type_radio"
							type="radio">
							<FileIcon :size="20" />
							<span class="option-title">
								{{ t('integration_mattermost', 'Upload files') }}
							</span>
						</CheckboxRadioSwitch>
						<CheckboxRadioSwitch
							:checked.sync="sendType"
							value="link"
							name="send_type_radio"
							type="radio">
							<LinkVariantIcon :size="20" />
							<span class="option-title">
								{{ t('integration_mattermost', 'Public links') }}
							</span>
						</CheckboxRadioSwitch>
					</div>
					<RadioElementSet v-if="sendType === 'link'"
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
					<div v-show="sendType === 'link'"
						class="expiration-field">
						<CheckboxRadioSwitch :checked.sync="expirationEnabled">
							{{ t('integration_mattermost', 'Set expiration date') }}
						</CheckboxRadioSwitch>
						<div class="spacer" />
						<DatetimePicker v-show="expirationEnabled"
							id="expiration-datepicker"
							v-model="expirationDate"
							:disabled-date="isDateDisabled"
							:placeholder="t('integration_mattermost', 'Expires on')"
							:clearable="true" />
					</div>
					<div v-show="sendType === 'link'"
						class="password-field">
						<CheckboxRadioSwitch :checked.sync="passwordEnabled">
							{{ t('integration_mattermost', 'Set link password') }}
						</CheckboxRadioSwitch>
						<div class="spacer" />
						<input v-show="passwordEnabled"
							id="password-input"
							v-model="password"
							type="text"
							:placeholder="passwordPlaceholder">
					</div>
					<span class="field-label">
						<CommentIcon />
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
					<AlertBoxIcon class="warning-icon" />
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
					<NcButton type="primary"
						:class="{ loading, okButton: true }"
						:disabled="!canValidate"
						@click="onSendClick">
						<template #icon>
							<SendIcon />
						</template>
						{{ sendType === 'file'
							? n('integration_mattermost', 'Send file', 'Send files', files.length)
							: n('integration_mattermost', 'Send link', 'Send links', files.length)
						}}
					</NcButton>
				</div>
			</div>
		</Modal>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect.js'
import Highlight from '@nextcloud/vue/dist/Components/Highlight.js'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch.js'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker.js'
import Modal from '@nextcloud/vue/dist/Components/Modal.js'
// import LoadingIcon from '@nextcloud/vue/dist/Components/LoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import Avatar from '@nextcloud/vue/dist/Components/Avatar.js'

import SendIcon from 'vue-material-design-icons/Send.vue'
import FileIcon from 'vue-material-design-icons/File.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PoundBoxIcon from 'vue-material-design-icons/PoundBox.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import PackageUpIcon from 'vue-material-design-icons/PackageUp.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import AlertBoxIcon from 'vue-material-design-icons/AlertBox.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import EyeIcon from 'vue-material-design-icons/Eye.vue'

import RadioElementSet from './RadioElementSet.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import MattermostIcon from './icons/MattermostIcon.vue'
import { humanFileSize } from '../utils.js'

const STATES = {
	IN_PROGRESS: 1,
	FINISHED: 2,
}

export default {
	name: 'SendFilesModal',

	components: {
		MattermostIcon,
		Multiselect,
		CheckboxRadioSwitch,
		DatetimePicker,
		Highlight,
		Modal,
		RadioElementSet,
		// LoadingIcon,
		NcButton,
		Avatar,
		SendIcon,
		PoundBoxIcon,
		FileIcon,
		LinkVariantIcon,
		PackageUpIcon,
		CommentIcon,
		CheckCircleIcon,
		AlertBoxIcon,
		CloseIcon,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			sendType: 'file',
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
				view: { label: t('integration_mattermost', 'View only'), icon: EyeIcon },
				edit: { label: t('integration_mattermost', 'Edit'), icon: PencilIcon },
			},
		}
	},

	computed: {
		warnAboutSendingDirectories() {
			return this.sendType === 'file' && this.files.findIndex((f) => f.type === 'dir') !== -1
		},
		onlyDirectories() {
			return this.files.filter((f) => f.type !== 'dir').length === 0
		},
		canValidate() {
			return this.selectedChannel !== null
				&& (this.sendType !== 'file' || !this.onlyDirectories)
				&& this.files.length > 0
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
			this.sendType = 'file'
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
			this.$emit('closed')
			this.reset()
		},
		setFiles(files) {
			this.files = files
		},
		onSendClick() {
			this.loading = true
			this.$emit('validate', {
				filesToSend: [...this.files],
				channelId: this.selectedChannel.id,
				channelName: this.selectedChannel.display_name,
				type: this.sendType,
				comment: this.comment,
				permission: this.selectedPermission,
				expirationDate: this.sendType === 'link' && this.expirationEnabled ? this.expirationDate : null,
				password: this.sendType === 'link' && this.passwordEnabled ? this.password : null,
			})
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
			}).catch((error) => {
				console.error(error)
			})
		},
		getFilePreviewUrl(fileId, fileType) {
			if (fileType === 'dir') {
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
			return generateUrl('/apps/integration_mattermost/teams/{teamId}/image', { teamId }) + '?useFallback=0'
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
	},
}
</script>

<style scoped lang="scss">
.mattermost-modal-content {
	//width: 100%;
	padding: 16px;
	display: flex;
	flex-direction: column;

	> * {
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

	.channel-select {
		height: 44px;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 16px 0 16px 0;
	}

	.multiselect-name {
		margin-left: 8px;
	}

	.option-title {
		margin-left: 8px;
	}
}

.spacer {
	flex-grow: 1;
}

.mattermost-footer {
	display: flex;
	padding-bottom: 16px;
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
