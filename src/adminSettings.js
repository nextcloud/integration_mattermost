/* jshint esversion: 6 */

/**
 * Nextcloud - Mattermost
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
import AdminSettings from './components/AdminSettings.vue'

const VueAdminSettings = Vue.extend(AdminSettings)
new VueAdminSettings().$mount('#mattermost_prefs')
