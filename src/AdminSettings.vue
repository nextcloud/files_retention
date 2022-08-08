<!--
  - @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
  -
  - @author Roeland Jago Douma <roeland@famdouma.nl>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<SettingsSection :title="t('files_retention', 'File retention')"
		:doc-url="docUrl"
		:description="t('files_retention', 'Define if files tagged with a specific tag should be deleted automatically after some time. This is useful for confidential documents.')">

		<table class="retention-rules-table">
			<thead>
				<tr>
					<th class="retention-heading__name">{{ t('files_retention', 'Tag') }}</th>
					<th class="retention-heading__amount">{{ t('files_retention','Retention') }}</th>
					<th class="retention-heading__unit">{{ t('files_retention','Time') }}</th>
					<th class="retention-heading__after">{{ t('files_retention','After') }}</th>
					<th class="retention-heading__active">{{ t('files_retention','Active') }}</th>
					<th class="retention-heading__action">{{ t('files_retention','Actions') }}</th>
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
						<MultiselectTags v-model="newTag"
							:disabled="loading"
							:multiple="false"
							:filter="filterAvailableTagList"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__amount">
						<TextField :value.sync="newAmount"
							:disabled="loading"
							type="text"
							:label="amountLabel"
							:placeholder="''" />
					</td>
					<td class="retention-rule__unit">
						<Multiselect v-model="newUnit"
							:disabled="loading"
							 :options="unitOptions"
							:allow-empty="false"
							track-by="id"
							label="label"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__after">
						<Multiselect v-model="newAfter"
							:disabled="loading"
							:options="afterOptions"
							:allow-empty="false"
							track-by="id"
							label="label"
							:close-on-select="true" />
					</td>
					<td class="retention-rule__active"></td>
					<td class="retention-rule__action">
						<Button type="tertiary"
							:disabled="loading"
							:aria-label="createLabel"
							@click="onClickCreate">
							<template #icon>
								<Plus :size="20" />
							</template>
						</Button>
					</td>
				</tr>
			</tbody>
		</table>

		<CheckboxRadioSwitch type="switch"
			:checked="notifyBefore"
			:loading="loadingNotifyBefore"
			@update:checked="onToggleNotifyBefore">
			{{ t('files_retention', 'Notify users a day before retention will delete a file') }}
		</CheckboxRadioSwitch>
	</SettingsSection>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'
import Plus from 'vue-material-design-icons/Plus'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import TextField from '@nextcloud/vue/dist/Components/TextField'

import RetentionRule from './Components/RetentionRule.vue'

import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'AdminSettings',

	components: {
		Button,
		CheckboxRadioSwitch,
		Multiselect,
		MultiselectTags,
		Plus,
		RetentionRule,
		SettingsSection,
		TextField,
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
			return t('files_retention','Number of days, weeks, months or years after which the files should be deleted')
		},

		createLabel() {
			return t('files_retention','Create new retention rule')
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
					}
				}
			)
		},

		async onClickCreate() {
			// When the value is unchanged, the Multiselect component returns the initial ID
			// Otherwise the entry from this.unitOptions
			const newTag = this.newTag?.id ?? this.newTag
			const newUnit = this.newUnit?.id ?? this.newUnit
			const newAfter = this.newAfter?.id ?? this.newAfter

			if (newTag < 0) {
				showError(t('files_retention', 'Invalid tag selected'))
				return
			}

			const tagName = OC.SystemTags.collection.get(newTag)?.attributes?.name

			try {
				await this.$store.dispatch('createNewRule', {
					tagid: newTag,
					timeamount: this.newAmount,
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
			padding: 10px;
		}

		&__amount {
			text-align: right;
		}

		&__action {
			padding: 0 10px;
		}
	}
}
</style>