<template>
	<NcDialog v-if="show"
		:name="title"
		:message="message"
		:container="null"
		@close="onClose(false)">
		<template #actions>
			<NcButton @click="onClose(false)">
				{{ cancelText }}
			</NcButton>
			<NcButton
				variant="primary"
				@click="onClose(true)">
				{{ confirmText }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'

export default {
	name: 'SlackDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
		message: {
			type: String,
			required: true,
		},
		confirmText: {
			type: String,
			required: false,
			default: t('integration_slack', 'Confirm'),
		},
		cancelText: {
			type: String,
			required: false,
			default: t('integration_slack', 'Cancel'),
		},
	},

	data() {
		return {
			show: true,
		}
	},

	methods: {
		onClose(res) {
			this.show = false
			this.$emit('close', res)
		},
	},
}
</script>
