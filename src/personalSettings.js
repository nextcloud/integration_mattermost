/* jshint esversion: 6 */

/**
 * Nextcloud - mattermost
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

import Vue from 'vue'
import './bootstrap.js'
import PersonalSettings from './components/PersonalSettings.vue'

/* TODO: remove this */
Vue.config.devtools = true

const VuePersonalSettings = Vue.extend(PersonalSettings)
new VuePersonalSettings().$mount('#slack_prefs')
