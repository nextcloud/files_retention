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
		<CheckboxRadioSwitch type="switch"
			:checked="notifyBefore"
			:loading="loadingNotifyBefore"
			@update:checked="onToggleNotifyBefore">
			{{ t('files_retention', 'Notify users a day before retention will delete a file') }}
		</CheckboxRadioSwitch>
	</SettingsSection>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'AdminSettings',

	components: {
		CheckboxRadioSwitch,
		SettingsSection,
	},

	data() {
		return {
			loadingNotifyBefore: false,
			notifyBefore: loadState('files_retention', 'notify_before'),
			docUrl: loadState('files_retention', 'doc-url'),
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
	},
}
</script>

<style scoped lang="scss">
</style>
