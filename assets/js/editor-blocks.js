(function (wp) {
	if (!wp || !wp.blocks || !wp.element || !wp.i18n) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var createElement = wp.element.createElement;
	var __ = wp.i18n.__;

	var blocks = [
		{
			slug: 'restaurant-order-meals',
			title: __('Restaurant Order Meals', 'restaurant-food-services'),
			description: __('Displays the meal ordering frontend.', 'restaurant-food-services')
		},
		{
			slug: 'restaurant-meal-plans',
			title: __('Restaurant Meal Plans', 'restaurant-food-services'),
			description: __('Displays the weekly meal plan wizard.', 'restaurant-food-services')
		},
		{
			slug: 'restaurant-catering',
			title: __('Restaurant Catering', 'restaurant-food-services'),
			description: __('Displays the catering request wizard.', 'restaurant-food-services')
		},
		{
			slug: 'restaurant-account',
			title: __('Restaurant Account Hub', 'restaurant-food-services'),
			description: __('Displays authentication and grouped account dashboard.', 'restaurant-food-services')
		},
		{
			slug: 'restaurant-signup',
			title: __('Restaurant Signup', 'restaurant-food-services'),
			description: __('Displays a frontend user signup interface.', 'restaurant-food-services')
		}
	];

	blocks.forEach(function (block) {
		registerBlockType('restaurant-food-services/' + block.slug, {
			apiVersion: 2,
			title: block.title,
			description: block.description,
			category: 'widgets',
			icon: 'store',
			supports: {
				html: false
			},
			edit: function () {
				return createElement(
					'div',
					{ className: 'restaurant-editor-shortcode-placeholder' },
					createElement('strong', null, block.title),
					createElement('p', null, block.description)
				);
			},
			save: function () {
				return null;
			}
		});
	});
})(window.wp);

