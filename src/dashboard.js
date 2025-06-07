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

import { linkTo } from '@nextcloud/router'
import { getCSPNonce } from '@nextcloud/auth'

__webpack_nonce__ = getCSPNonce() // eslint-disable-line
__webpack_public_path__ = linkTo('integration_mattermost', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('mattermost_notifications', async (el, { widget }) => {
		const { default: Vue } = await import(/* webpackChunkName: "dashboard-lazy" */'vue')
		Vue.mixin({ methods: { t, n } })
		const { default: Dashboard } = await import(/* webpackChunkName: "dashboard-lazy" */'./views/Dashboard.vue')
		const View = Vue.extend(Dashboard)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})
})
