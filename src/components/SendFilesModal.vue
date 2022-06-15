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
						<img :src="getFilePreviewUrl(f.id)"
							class="file-image">
						<span>
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
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Button from '@nextcloud/vue/dist/Components/Button'
import SendIcon from 'vue-material-design-icons/Send'
import FileIcon from 'vue-material-design-icons/File'
import PoundBoxIcon from 'vue-material-design-icons/PoundBox'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import MattermostIcon from './MattermostIcon'

export default {
	name: 'SendFilesModal',

	components: {
		MattermostIcon,
		Multiselect,
		Highlight,
		Modal,
		Button,
		SendIcon,
		PoundBoxIcon,
		FileIcon,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			query: '',
			files: [],
			channels: [],
			selectedChannel: null,
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
			this.channels = []
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
			this.$emit('validate', this.files, this.selectedChannel.id, this.selectedChannel.display_name)
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
	},
}
</script>

<style scoped lang="scss">
.mattermost-modal-content {
	padding: 16px;
	display: flex;
	flex-direction: column;

	.modal-title {
		display: flex;
		justify-content: center;
		margin-bottom: 16px;
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
		margin: 0 0 12px 32px;
		.file {
			display: flex;
			align-items: center;
			margin: 4px 0;

			img {
				width: 32px;
				height: auto;
				margin-right: 12px;
			}
		}
	}

	.userInput {
		width: 100%;
		margin: 0 0 28px 0;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 16px 0 16px 0;
	}

	.multiselect-name {
		margin-left: 8px;
	}
}

.mattermost-footer {
	display: flex;
	margin-top: 16px;
	.spacer {
		flex-grow: 1;
	}
}

.field-label {
	display: flex;
	align-items: center;
	margin-bottom: 12px;
	span {
		margin-left: 8px;
	}
}
</style>
