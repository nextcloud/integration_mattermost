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
						<PoundBoxIcon />
						<Highlight
							:text="t('integration_mattermost', '{channelName} [{teamName}]', { channelName: option.display_name, teamName: option.team_display_name })"
							:search="query"
							class="multiselect-name" />
					</template>
					<template #singleLabel="{option}">
						<PoundBoxIcon />
						<span class="multiselect-name">
							{{ t('integration_mattermost', '{channelName} in {teamName}', { channelName: option.display_name, teamName: option.team_display_name }) }}
						</span>
					</template>
					<template #noOptions>
						{{ t('integration_mattermost', 'Start typing to search') }}
					</template>
				</Multiselect>
				<span class="field-label">
					<PackageUpIcon />
					<span>
						{{ t('integration_mattermost', 'Send') }}
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
				<input v-model="comment"
					type="text"
					:placeholder="commentPlaceholder">
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
						{{ t('integration_mattermost', 'Send files') }}
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
import SendIcon from 'vue-material-design-icons/Send'
import FileIcon from 'vue-material-design-icons/File'
import PoundBoxIcon from 'vue-material-design-icons/PoundBox'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant'
import PackageUpIcon from 'vue-material-design-icons/PackageUp'
import CommentIcon from 'vue-material-design-icons/Comment'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle'

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
		SendIcon,
		PoundBoxIcon,
		FileIcon,
		LinkVariantIcon,
		PackageUpIcon,
		CommentIcon,
		CheckCircleIcon,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			sendType: 'link',
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
	},

	methods: {
		showModal() {
			this.show = true
			// once the modal is opened, focus on the multiselect input
			/*
			this.$nextTick(() => {
				this.$refs.multiselect.$el.querySelector('input').focus()
			})
			*/
		},
		closeModal() {
			this.show = false
			this.$emit('closed')
			this.selectedChannel = null
			this.files = []
			this.fileStates = {}
			this.channels = []
			this.comment = ''
		},
		setFiles(files) {
			this.files = files
		},
		updateselectedChannel(newValue) {
			if (newValue !== null) {
				this.selectedChannel = newValue
				console.debug('selected', this.selectedChannel)
			}
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

		&.field-label {
			display: flex;
			align-items: center;
			margin-top: 12px;
			span {
				margin-left: 8px;
			}
		}
		&:not(.field-label) {
			margin-left: 32px;
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
