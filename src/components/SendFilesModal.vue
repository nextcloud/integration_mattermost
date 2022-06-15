<template>
	<div class="mattermost-modal-container">
		<Modal v-if="show"
			size="large"
			@close="closeModal">
			<div class="mattermost-modal-content">
				<h2>
					{{ t('integration_mattermost', 'Send files to Mattermost') }}
				</h2>
				<span class="field-label">
					{{ t('integration_mattermost', 'Channel') }}
				</span>
				<p class="settings-hint">
					{{ t('integration_mattermost', 'plop') }}
				</p>
				<div class="mattermost-footer">
					<Button
						@click="closeModal">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_mattermost', 'Cancel') }}
					</Button>
					<Button class="primary"
						:class="{ loading }"
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
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Button from '@nextcloud/vue/dist/Components/Button'
import CloseIcon from 'vue-material-design-icons/Close'
import SendIcon from 'vue-material-design-icons/Send'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SendFilesModal',

	components: {
		Modal,
		Button,
		CloseIcon,
		SendIcon,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			files: [],
			channels: [],
			selectedItem: { id: 222, name: 'super-chan', display_name: 'Super channel' },
		}
	},

	computed: {
		canValidate() {
			return this.selectedItem !== null
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
			this.$nextTick(() => {
				// this.$refs.multiselect.$el.querySelector('input').focus()
			})
		},
		closeModal() {
			this.show = false
			this.$emit('closed')
		},
		setFiles(files) {
			this.files = files
		},
		updateSelectedItem(newValue) {
			if (newValue !== null) {
				this.selectedItem = newValue
				console.debug('selected', this.selectedItem)
			}
		},
		onSendClick() {
			this.loading = true
			this.$emit('validate', this.files, this.selectedItem.id, this.selectedItem.display_name)
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
	},
}
</script>

<style scoped lang="scss">
.mattermost-modal-content {
	padding: 16px;
	max-width: 400px;
	display: flex;
	flex-direction: column;

	input[type='text'] {
		width: 100%;
	}

	.userInput {
		width: 100%;
		margin: 0 0 28px 0;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 16px 0 16px 0;
	}
}

.mattermost-footer {
	margin-top: 16px;
	.primary {
		float: right;
	}
	.icon {
		opacity: 1;
	}
}

.field-label {
	display: flex;
	align-items: center;
	height: 36px;
	margin: 8px 0 0 0;
	.icon {
		width: 32px;
	}
}
</style>
