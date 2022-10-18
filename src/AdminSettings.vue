<!--
  - SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
  - SPDX-License-Identifier: AGPL-3.0-only
  -->
<template>
	<NcSettingsSection :title="t('files_retention', 'File retention & automatic deletion')"
		:doc-url="docUrl"
		:description="t('files_retention', 'Define if files tagged with a specific tag should be deleted automatically after some time. This is useful for confidential documents.')">
		<table class="retention-rules-table">
			<thead>
				<tr>
					<th class="retention-heading__name">
						{{ t('files_retention', 'Files tagged with') }}
					</th>
					<th class="retention-heading__amount">
						{{ t('files_retention','Retention') }}
					</th>
					<th class="retention-heading__unit">
						{{ t('files_retention','Time') }}
					</th>
					<th class="retention-heading__after">
						{{ t('files_retention','From date of') }}
					</th>
					<th class="retention-heading__action"></th>
				</tr>
			</thead>
			<tbody>
				<RetentionRule v-for="rule in retentionRules"
					:key="rule.id"
					v-bind="rule">
					{{ rule.tagid }}
				</RetentionRule>

				<tr>
					<td class="retention-rule__name">
						<NcMultiselectTags v-model="newTag"
							:disabled="loading"
							:multiple="false"
							:filter="filterAvailableTagList"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__amount">
						<NcTextField :value.sync="newAmount"
							:disabled="loading"
							type="text"
							:label="amountLabel"
							:placeholder="''" />
					</td>
					<td class="retention-rule__unit">
						<NcMultiselect v-model="newUnit"
							:disabled="loading"
							:options="unitOptions"
							:allow-empty="false"
							track-by="id"
							label="label"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__after">
						<NcMultiselect v-model="newAfter"
							:disabled="loading"
							:options="afterOptions"
							:allow-empty="false"
							track-by="id"
							label="label"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__action">
						<NcButton type="tertiary"
							:disabled="loading"
							:aria-label="createLabel"
							@click="onClickCreate">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('files_retention', 'Create') }}
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<NcCheckboxRadioSwitch type="switch"
			:checked="notifyBefore"
			:loading="loadingNotifyBefore"
			@update:checked="onToggleNotifyBefore">
			{{ t('files_retention', 'Notify owner a day before a file is automatically deleted') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect.js'
import NcMultiselectTags from '@nextcloud/vue/dist/Components/NcMultiselectTags.js'
import Plus from 'vue-material-design-icons/Plus.vue'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import RetentionRule from './Components/RetentionRule.vue'

import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'AdminSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcMultiselect,
		NcMultiselectTags,
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
			newUnit: 0,

			afterOptions: [
				{ id: 0, label: t('files_retention', 'Creation') },
				{ id: 1, label: t('files_retention', 'Last modification') },
			],
			newAfter: 0,

			newAmount: '14', // FIXME TextField does not accept numbers …

			newTag: -1,
			tagOptions: [],
			filterAvailableTagList: (tag) => {
				return !this.tagIdsWithRule.includes(tag.id)
			},
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
			return t('files_retention', 'Number of days, weeks, months or years after which the files should be deleted')
		},

		createLabel() {
			return t('files_retention', 'Create new retention rule')
		},
	},

	async mounted() {
		try {
			await OC.SystemTags.collection.fetch({})
			await this.$store.dispatch('loadRetentionRules')

			this.loading = false
		} catch (e) {
			showError(t('files_retention', 'An error occurred while loading the existing retention rules'))
			console.error(e)
		}
	},

	methods: {
		onToggleNotifyBefore() {
			this.loadingNotifyBefore = true
			const newNotifyBefore = !this.notifyBefore

			OCP.AppConfig.setValue(
				'files_retention',
				'notify_before',
				newNotifyBefore ? 'yes' : 'no',
				{
					success() {
						if (newNotifyBefore) {
							showSuccess(t('files_retention', 'Users are now notified one day before a file or folder is being deleted'))
						} else {
							showWarning(t('files_retention', 'Users are no longer notified before a file or folder is being deleted'))
						}

						this.loadingNotifyBefore = false
						this.notifyBefore = newNotifyBefore
					},
					error() {
						this.loadingNotifyBefore = false
						showError(t('files_retention', 'An error occurred while changing the setting'))
					},
				}
			)
		},

		async onClickCreate() {
			// When the value is unchanged, the Multiselect component returns the initial ID
			// Otherwise the entry from this.unitOptions
			const newTag = this.newTag?.id ?? this.newTag
			const newUnit = this.newUnit?.id ?? this.newUnit
			const newAfter = this.newAfter?.id ?? this.newAfter
			const newAmount = parseInt(this.newAmount, 10)

			if (newTag < 0) {
				showError(t('files_retention', 'Invalid tag selected'))
				return
			}

			const tagName = OC.SystemTags.collection.get(newTag)?.attributes?.name

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
			this.newTag = -1
			this.newAmount = '14'
			this.newUnit = 0
			this.newAfter = 0
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
		&__amount,
		&__unit,
		&__after,
		&__active,
		&__action {
			color: var(--color-text-maxcontrast);
			padding: 10px 10px 10px 0;
		}
		&__amount {
			text-align: right;
		}

		&__action {
			padding-left: 10px;
			flex-direction: row-reverse;
			display: flex;
		}
	}

	.retention-heading {
		&__name,
		&__unit,
		&__after,
		&__active,
		&__action {
			padding-left: 13px;
		}

		&__amount {
			padding-right: 23px;
		}
	}

	.retention-rule {
		&__amount {
			::v-deep .input-field__input {
				text-align: right;
			}
		}
	}
}
</style>
