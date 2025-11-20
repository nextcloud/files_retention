<!--
  - SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
  - SPDX-License-Identifier: AGPL-3.0-only
  -->
<template>
	<NcSettingsSection :name="t('files_retention', 'File retention & automatic deletion')"
		:doc-url="docUrl"
		:description="t('files_retention', 'Define if files tagged with a specific tag should be deleted automatically after some time. This is useful for confidential documents.')">
		<table class="retention-rules-table">
			<thead>
				<tr>
					<th class="retention-heading__name">
						{{ t('files_retention', 'Files tagged with') }}
					</th>
					<th class="retention-heading__time">
						{{ t('files_retention','Retention time') }}
					</th>
					<th class="retention-heading__after">
						{{ t('files_retention','From date of') }}
					</th>
					<th class="retention-heading__action" />
				</tr>
			</thead>
			<tbody>
				<RetentionRule v-for="rule in retentionRules"
					:key="rule.id"
					:tags="tags"
					v-bind="rule">
					{{ rule.tagid }}
				</RetentionRule>

				<tr>
					<td class="retention-rule__name">
						<NcSelectTags v-model="newTag"
							:disabled="loading"
							:multiple="false"
							:clearable="false"
							:options-filter="filterAvailableTagList" />
					</td>
					<td class="retention-rule__time">
						<NcTextField v-model="newAmount"
							:disabled="loading"
							type="text"
							:label="amountLabel"
							:aria-label="amountAriaLabel"
							:placeholder="''" />
						<NcSelect v-model="newUnit"
							:disabled="loading"
							:options="unitOptions"
							:allow-empty="false"
							:clearable="false"
							track-by="id"
							label="label" />
					</td>
					<td class="retention-rule__after">
						<NcSelect v-model="newAfter"
							:disabled="loading"
							:options="afterOptions"
							:allow-empty="false"
							:clearable="false"
							track-by="id"
							label="label" />
					</td>
					<td class="retention-rule__action">
						<div class="retention-rule__action--button-aligner">
							<NcButton variant="success"
								:disabled="loading || newTag < 0"
								:aria-label="createLabel"
								@click="onClickCreate">
								<template #icon>
									<Plus :size="20" />
								</template>
								{{ t('files_retention', 'Create') }}
							</NcButton>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<NcCheckboxRadioSwitch type="switch"
			:model-value="notifyBefore"
			:loading="loadingNotifyBefore"
			@update:modelValue="onToggleNotifyBefore">
			{{ t('files_retention', 'Notify owner a day before a file is automatically deleted') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSelectTags from '@nextcloud/vue/components/NcSelectTags'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Plus from 'vue-material-design-icons/Plus.vue'

import RetentionRule from './Components/RetentionRule.vue'
import { fetchTags } from './services/api.ts'

import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

export default {
	name: 'AdminSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcSelectTags,
		Plus,
		RetentionRule,
		NcSettingsSection,
		NcTextField,
	},

	data() {
		return {
			loading: true,
			loadingNotifyBefore: false,
			notifyBefore: loadState('files_retention', 'notify_before'),
			docUrl: loadState('files_retention', 'doc-url'),

			unitOptions: [
				{ id: 0, label: t('files_retention', 'Days') },
				{ id: 1, label: t('files_retention', 'Weeks') },
				{ id: 2, label: t('files_retention', 'Months') },
				{ id: 3, label: t('files_retention', 'Years') },
			],
			newUnit: {},

			afterOptions: [
				{ id: 0, label: t('files_retention', 'Creation') },
				{ id: 1, label: t('files_retention', 'Last modification') },
			],
			newAfter: {},

			newAmount: '14', // FIXME TextField does not accept numbers …

			newTag: null,
			tagOptions: [],
			filterAvailableTagList: (tag) => {
				return !this.tagIdsWithRule.includes(tag.id)
			},
			tags: [],
		}
	},

	computed: {
		retentionRules() {
			return this.$store.getters.getRetentionRules()
		},

		tagIdsWithRule() {
			return this.$store.getters.getTagIdsWithRule()
		},

		amountLabel() {
			return t('files_retention', 'Time units')
		},

		amountAriaLabel() {
			return t('files_retention', 'Number of days, weeks, months or years after which the files should be deleted')
		},

		createLabel() {
			return t('files_retention', 'Create new retention rule')
		},
	},

	async mounted() {
		try {
			this.tags = await fetchTags()
			await this.$store.dispatch('loadRetentionRules')

			this.resetForm()

			this.loading = false
		} catch (e) {
			showError(t('files_retention', 'An error occurred while loading the existing retention rules'))
			console.error(e)
		}
	},

	methods: {
		t,

		onToggleNotifyBefore() {
			this.loadingNotifyBefore = true
			const newNotifyBefore = !this.notifyBefore

			OCP.AppConfig.setValue(
				'files_retention',
				'notify_before',
				newNotifyBefore ? 'yes' : 'no',
				{
					success: function() {
						if (newNotifyBefore) {
							showSuccess(t('files_retention', 'Users are now notified one day before a file or folder is being deleted'))
						} else {
							showWarning(t('files_retention', 'Users are no longer notified before a file or folder is being deleted'))
						}

						this.loadingNotifyBefore = false
						this.notifyBefore = newNotifyBefore
					}.bind(this),
					error: function() {
						this.loadingNotifyBefore = false
						showError(t('files_retention', 'An error occurred while changing the setting'))
					}.bind(this),
				},
			)
		},

		async onClickCreate() {
			// When the value is unchanged, the Multiselect component returns the initial ID
			// Otherwise the entry from this.unitOptions
			const newTag = this.newTag?.id ?? this.newTag
			const newUnit = this.newUnit?.id ?? this.newUnit
			const newAfter = this.newAfter?.id ?? this.newAfter
			const newAmount = parseInt(this.newAmount, 10)

			if (newTag === null || newTag < 0) {
				showError(t('files_retention', 'Invalid tag selected'))
				return
			}

			const tagName = this.tags.find((tag) => tag.id === newTag)?.displayName

			if (this.tagIdsWithRule.includes(newTag)) {
				showError(t('files_retention', 'Tag {tagName} already has a retention rule', { tagName }))
				return
			}

			if (newUnit < 0 || newUnit > 3) {
				showError(t('files_retention', 'Invalid unit option'))
				return
			}

			if (newAfter < 0 || newAfter > 1) {
				showError(t('files_retention', 'Invalid action option'))
				return
			}

			if (isNaN(newAmount) || newAmount < 1) {
				showError(t('files_retention', 'Invalid retention time'))
				return
			}

			try {
				await this.$store.dispatch('createNewRule', {
					tagid: newTag,
					timeamount: newAmount,
					timeunit: newUnit,
					timeafter: newAfter,
				})

				showSuccess(t('files_retention', 'Retention rule for tag {tagName} saved', { tagName }))
				this.resetForm()
			} catch (e) {
				showError(t('files_retention', 'Failed to save retention rule for tag {tagName}', { tagName }))
				console.error(e)
			}
		},

		resetForm() {
			this.newTag = null
			this.newAmount = '14'
			this.newUnit = this.unitOptions[0]
			this.newAfter = this.afterOptions[0]
		},
	},
}
</script>

<style scoped lang="scss">
.retention-rules-table {
	width: 100%;
	min-height: 50px;
	padding-top: 5px;
	max-width: 580px;

	.retention-heading,
	.retention-rule {
		&__name,
		&__time,
		&__after,
		&__active,
		&__action {
			color: var(--color-text-maxcontrast);
			padding: 10px 10px 10px 0;
			vertical-align: bottom;
		}

		&__time {
			text-align: center;
			min-width: 320px;
		}

		&__action {
			padding-left: 10px;
			flex-direction: row-reverse;
			display: flex;

			&--button-aligner {
				margin-top: 6px;
			}
		}
	}

	.retention-heading {
		&__name,
		&__time,
		&__after,
		&__active,
		&__action {
			padding-left: 13px;
		}
	}

	.retention-rule {
		&__time {
			> div {
				width: 49%;
				min-width: 0;
				display: inline-block;
			}

			:deep(.input-field__input) {
				text-align: right;
			}
		}
	}
}
</style>
