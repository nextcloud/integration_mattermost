<template>
	<div class="mattermost-modal-container">
		<Modal v-if="show"
			size="normal"
			@close="closeModal">
			<div class="mattermost-modal-content">
				<h2 class="modal-title">
					<MattermostIcon />
					<span>
						{{ t('integration_mattermost', 'Send files to Mattermost') }}
					</span>
				</h2>
				<span class="field-label">
					<FileIcon />
					<span>
						{{ t('integration_mattermost', 'Files') }}
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
							:src="getFilePreviewUrl(f.id)"
							class="file-image">
						<span class="file-name">
							{{ f.name }}
						</span>
					</div>
				</div>
				<span class="field-label">
					<PoundBoxIcon />
					<span>
						{{ t('integration_mattermost', 'Channel') }}
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
							:is-no-user="true"
							:url="getTeamIconUrl(option.team_id)"
							display-name="#" />
						<Highlight
							:text="t('integration_mattermost', '[{teamName}] {channelName}', { channelName: option.display_name, teamName: option.team_display_name })"
							:search="query"
							class="multiselect-name" />
					</template>
					<template #singleLabel="{option}">
						<Avatar
							:is-no-user="true"
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
				<Button class="advanced-switch"
					@click="showAdvanced = !showAdvanced">
					<template #icon>
						<ChevronDownIcon v-if="showAdvanced" />
						<ChevronRightIcon v-else />
					</template>
					{{ showAdvanced ? t('integration_mattermost', 'Hide advanced options') : t('integration_mattermost', 'Show advanced options') }}
				</Button>
				<div v-show="showAdvanced"
					class="advanced-options">
					<span class="field-label">
						<PackageUpIcon />
						<span>
							{{ t('integration_mattermost', 'Type') }}
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
					<span class="field-label">
						<CommentIcon />
						<span>
							{{ t('integration_mattermost', 'Comment') }}
						</span>
					</span>
					<div class="input-wrapper">
						<input v-model="comment"
							type="text"
							:placeholder="commentPlaceholder">
					</div>
				</div>
				<div class="mattermost-footer">
					<Button
						@click="closeModal">
						{{ t('integration_mattermost', 'Cancel') }}
					</Button>
					<div class="spacer" />
					<Button type="primary"
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
					</Button>
				</div>
			</div>
		</Modal>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Highlight from '@nextcloud/vue/dist/Components/Highlight'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'
// import LoadingIcon from '@nextcloud/vue/dist/Components/LoadingIcon'
import Button from '@nextcloud/vue/dist/Components/Button'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import SendIcon from 'vue-material-design-icons/Send'
import FileIcon from 'vue-material-design-icons/File'
import PoundBoxIcon from 'vue-material-design-icons/PoundBox'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant'
import PackageUpIcon from 'vue-material-design-icons/PackageUp'
import CommentIcon from 'vue-material-design-icons/Comment'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import MattermostIcon from './MattermostIcon'

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
		Highlight,
		Modal,
		// LoadingIcon,
		Button,
		Avatar,
		SendIcon,
		PoundBoxIcon,
		FileIcon,
		LinkVariantIcon,
		PackageUpIcon,
		CommentIcon,
		CheckCircleIcon,
		ChevronRightIcon,
		ChevronDownIcon,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			showAdvanced: false,
			sendType: 'file',
			comment: '',
			query: '',
			files: [],
			fileStates: {},
			channels: [],
			selectedChannel: null,
			STATES,
			commentPlaceholder: t('integration_mattermost', 'Sent from my Nextcloud'),
		}
	},

	computed: {
		canValidate() {
			return this.selectedChannel !== null
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
			this.showAdvanced = false
			this.sendType = 'file'
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
			this.$emit('validate',
				this.selectedChannel.id,
				this.selectedChannel.display_name,
				this.sendType,
				this.comment || this.commentPlaceholder
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
			}).catch((error) => {
				console.error(error)
			})
		},
		getFilePreviewUrl(fileId) {
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
	},
}
</script>

<style scoped lang="scss">
.mattermost-modal-content {
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

	> *:not(.field-label):not(.advanced-switch):not(.advanced-options):not(.mattermost-footer),
	.advanced-options > *:not(.field-label) {
		margin-left: 32px;
	}

	.advanced-options {
		display: flex;
		flex-direction: column;
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
			}

			.check-icon {
				color: var(--color-success);
			}
		}
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

.mattermost-footer {
	display: flex;
	.spacer {
		flex-grow: 1;
	}
}
</style>
