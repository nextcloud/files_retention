<!--
  - SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
  - SPDX-License-Identifier: AGPL-3.0-only
  -->
<template>
	<tr>
		<td class="retention-rule__name">
			{{ tagName }}
		</td>
		<td class="retention-rule__time">
			{{ getAmountAndUnit }}
		</td>
		<td class="retention-rule__after">
			{{ getAfter }}
		</td>
		<td class="retention-rule__action">
			<NcButton type="tertiary"
				:aria-label="deleteLabel"
				@click="onClickDelete">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Delete from 'vue-material-design-icons/Delete.vue'

import { showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'RetentionRule',

	components: {
		NcButton,
		Delete,
	},

	props: {
		id: {
			type: Number,
			required: true,
		},
		tagid: {
			type: Number,
			required: true,
		},
		timeunit: {
			type: Number,
			required: true,
		},
		timeamount: {
			type: Number,
			required: true,
		},
		timeafter: {
			type: Number,
			required: true,
		},
		hasJob: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		tagName() {
			return OC.SystemTags.collection.get(this.tagid)?.attributes?.name
		},

		getAmountAndUnit() {
			switch (this.timeunit) {
			case 0:
				return n('files_retention', '%n day', '%n days', this.timeamount)
			case 1:
				return n('files_retention', '%n week', '%n weeks', this.timeamount)
			case 2:
				return n('files_retention', '%n month', '%n months', this.timeamount)
			default:
				return n('files_retention', '%n year', '%n years', this.timeamount)
			}
		},

		getAfter() {
			switch (this.timeafter) {
			case 0:
				return t('files_retention', 'Creation')
			default:
				return t('files_retention', 'Last modification')
			}
		},

		deleteLabel() {
			return t('files_retention', 'Delete retention rule for tag {tagName}', { tagName: this.tagName })
		},
	},

	methods: {
		async onClickDelete() {
			await this.$store.dispatch('deleteRetentionRule', this.id)
			showSuccess(t('files_retention', 'Retention rule for tag {tagName} has been deleted', { tagName: this.tagName }))
		},
	},
}
</script>

<style scoped lang="scss">
.retention-rule {
	&__name,
	&__time,
	&__after,
	&__active,
	&__action {
		border-top: 1px solid var(--color-border);
		text-overflow: ellipsis;
		max-width: 200px;
		white-space: nowrap;
		overflow: hidden;
		padding: 10px 10px 10px 13px;
	}

	&__time {
		text-align: center;
	}

	&__action {
		padding-left: 10px;
		flex-direction: row-reverse;
		display: flex;
	}
}
</style>
