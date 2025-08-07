(function ($) {
  'use strict';

  $(document).ready(function () {
    const $postTypeSelect = $('#postforge_post_type');
    const $dynamicFields = $('#postforge-dynamic-fields');

    if ($postTypeSelect.length) {
      $postTypeSelect.on('change', function () {
        const postType = $(this).val();

        if (!postType) {
          $dynamicFields.html('');
          return;
        }

        $dynamicFields.html('<p>Loading...</p>');

        $.post(
          postforge_ajax.ajax_url,
          {
            action: 'postforge_get_post_type_data',
            nonce: postforge_ajax.nonce,
            post_type: postType,
          },
          function (response) {
            if (response.success) {
              let html = '';

              const savedTaxonomies = window.postforge_form_data?.taxonomies || [];
              const savedCustomFields = window.postforge_form_data?.custom_fields || [];

              if (response.data.taxonomies.length > 0) {
                html += '<h2>Taxonomies</h2>';
                response.data.taxonomies.forEach((tax) => {
                  const checked = savedTaxonomies.includes(tax.slug) ? 'checked' : '';
                  html += `
                    <label style="display:block;margin-bottom:5px;">
                      <input type="checkbox" name="postforge_taxonomies[]" value="${tax.slug}" ${checked}>
                      ${tax.label}
                    </label>
                  `;
                });
              } else {
                html += '<p>No taxonomies found for this post type.</p>';
              }

              if (response.data.meta_keys.length > 0) {
                html += '<h2>Custom Fields</h2>';
                html += '<table class="widefat"><thead><tr><th>Field</th><th>Label Override</th><th>Required?</th></tr></thead><tbody>';

                response.data.meta_keys.forEach((field) => {
                  const saved = savedCustomFields.find((f) => f.meta_key === field.meta_key);
                  const enabled = saved ? 'checked' : '';
                  const label = saved ? saved.label : field.meta_key;
                  const required = saved && saved.required ? 'checked' : '';

                  html += `
                    <tr>
                      <td>
                        <label>
                          <input type="checkbox" name="postforge_custom_fields[${field.meta_key}][enabled]" value="1" ${enabled}>
                          ${field.meta_key}
                        </label>
                      </td>
                      <td>
                        <input type="text" name="postforge_custom_fields[${field.meta_key}][label]" value="${label}">
                      </td>
                      <td>
                        <input type="checkbox" name="postforge_custom_fields[${field.meta_key}][required]" value="1" ${required}>
                      </td>
                    </tr>
                  `;
                });

                html += '</tbody></table>';
              } else {
                html += '<p>No custom fields found for this post type.</p>';
              }

              $dynamicFields.html(html);
            } else {
              $dynamicFields.html('<p>Error loading data.</p>');
            }
          }
        );
      });

      // If editing existing form, trigger change immediately
      if ($postTypeSelect.val()) {
        $postTypeSelect.trigger('change');
      }
    }
  });
})(jQuery);

jQuery(document).ready(function ($) {
	$('.shortcode-copy').on('click', function () {
		const shortcode = $(this).data('shortcode');
		navigator.clipboard.writeText(shortcode).then(() => {
			const feedback = $(this).siblings('.copy-feedback');
			feedback.fadeIn(150).delay(1000).fadeOut(1500);
		});
	});
});
