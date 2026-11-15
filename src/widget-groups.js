/**
 * The options of a widget menu, one group per connected restaurant.
 */
import { createElement as el } from '@wordpress/element';

export default function widgetGroups( restaurants, list ) {
	return restaurants
		.filter( ( restaurant ) => restaurant[ list ].length )
		.map( ( restaurant, index ) =>
			el(
				'optgroup',
				{ key: index, label: restaurant.name },
				restaurant[ list ].map( ( widget ) =>
					el(
						'option',
						{ key: widget.id, value: widget.id },
						widget.name
					)
				)
			)
		);
}
