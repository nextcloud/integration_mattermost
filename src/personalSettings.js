/* jshint esversion: 6 */

/**
 * Nextcloud - Slack
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 */

import Vue from 'vue'
import './bootstrap.js'
import PersonalSettings from './components/PersonalSettings.vue'

const VuePersonalSettings = Vue.extend(PersonalSettings)
new VuePersonalSettings().$mount('#slack_prefs')
