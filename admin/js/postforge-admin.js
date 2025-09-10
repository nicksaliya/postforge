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
              html += '<div id="postforge-accordion" class="postforge-accordion">';
              html += loadTaxonomiesHtml(response);
              html += loadCustomFieldsHtml(response);
              html += '</div>';
              $dynamicFields.html(html);
              if (response.data.meta_keys.length > 0) {
                // Now init accordion + sortable
                initAccordionSortable($dynamicFields);
              }


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

    function loadTaxonomiesHtml(response) {
      let html = '';
      const savedTaxonomies = window.postforge_form_data?.taxonomies || [];
      const savedTaxonomiesData = window.postforge_form_data?.taxonomies_data || [];
      if (response.data.taxonomies.length > 0) {
        html += '<h2>Taxonomies</h2>';
        html += `<div class="postforge-field-group taxonomies-section" data-key="taxonomies">`;
        response.data.taxonomies.forEach((tax) => {
          const checked = savedTaxonomies.includes(tax.slug) ? 'checked' : '';
          const savedType = savedTaxonomiesData[tax.slug]?.type || '';
          html += `
              <h3>${escapeHtml(tax.label)}</h3>
              <div class="taxonomy-container">
                  <div class="taxonomy-checkbox">
                    <p>Select ${escapeHtml(tax.label)}</p>
                    <label style="display:block;margin-bottom:5px;">
                      <input type="checkbox" name="postforge_taxonomies[]" value="${tax.slug}" ${checked}>
                      ${tax.label}
                    </label>
                  </div>
                  <div class="taxonomy-field-type">
                    <p>Field Type</p>
                    <select name="postforge_taxonomies_data[${tax.slug}][type]">
                      <option value="select" ${savedType === 'select' ? 'selected' : ''}>Select</option>
                      <option value="checkbox" ${savedType === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                      <option value="radio" ${savedType === 'radio' ? 'selected' : ''}>Radio Button</option>
                      <option value="multiselect" ${savedType === 'multiselect' ? 'selected' : ''}>Multiselect</option>
                    </select>
                  </div>
              </div>
            `;
        });
        html += '</div>';
      } else {
        html += '<p>No taxonomies found for this post type.</p>';
      }
      return html;
    }



    function loadCustomFieldsHtml(response) {
      let html = '';
      const savedCustomFields = window.postforge_form_data?.custom_fields || [];

      if (!response || !response.data || !Array.isArray(response.data.meta_keys) || response.data.meta_keys.length === 0) {
        html += '<p>No custom fields found for this post type.</p>';
        return html;
      }

      html += '<h2>Custom Fields</h2>';
      //html += '<div id="postforge-accordion" class="postforge-accordion">';

      response.data.meta_keys.forEach((field, index) => {
        // Defensive: ensure meta_key exists
        if (!field || !field.meta_key) return;

        const saved = savedCustomFields.find(f => f.meta_key === field.meta_key) || {};

        html += `
          <div class="postforge-field-group" data-key="${escapeHtml(field.meta_key)}">
            <h3>${escapeHtml(field.label || field.meta_key)}</h3>
            <div>
              ${renderFieldRows(field, saved, index)}
            </div>
          </div>
        `;
      });

      //html += '</div>';
      return html;
    }

    /**
     * Init accordion + sortable
     */
    function initAccordionSortable($scope) {
      const $accordion = $scope.find('#postforge-accordion');
      if (!$accordion.length) return;

      if ($accordion.hasClass('ui-accordion')) {
        try { $accordion.accordion('destroy'); } catch (e) { }
      }

      $accordion.accordion({
        header: 'h3',
        collapsible: true,
        active: false,
        heightStyle: 'content',
        beforeActivate: function (event, ui) {
          // Remove all signs first
          $accordion.find('h3 .toggle-icon').text('+');

          // Add minus to the one being opened
          if (ui.newHeader.length) {
            ui.newHeader.find('.toggle-icon').text('–');
          }
        }
      });

      // Add span for icons if not already there
      $accordion.find('h3').each(function () {
        if (!$(this).find('.toggle-icon').length) {
          $(this).prepend('<span class="toggle-icon">+</span> ');
        }
      });

      // Sortable
      $accordion.sortable({
        axis: 'y',
        handle: 'h3',
        stop: function () {
          $accordion.accordion('refresh');
          $accordion.find('.postforge-field-group').each(function (i) {
            $(this).find('.field-order').val(i + 1);
          });
        }
      });
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

document.addEventListener("DOMContentLoaded", () => {
  const postTypeSelect = document.querySelector("#postforge_post_type");
  const checkbox = document.querySelector("#postforge_login_required_permission");
  const rolesWrapper = document.querySelector("#postforge_roles_wrapper");
  const sendData = () => {
    const postType = postTypeSelect.value.trim();
    const loginRequired = checkbox.checked ? 1 : 0;

    if (loginRequired != 1) {
      // Clear old roles
      rolesWrapper.innerHTML = "";
      return;
    }


    // Validation: Make sure post type is not empty
    if (!postType) {
      console.warn("⚠️ Please select a post type before continuing.");
      return;
    }

    // Prepare data
    const data = new URLSearchParams();
    data.append("action", "postforge_get_available_user_roles");
    data.append("nonce", postforge_ajax.nonce);
    data.append("post_type", postType);
    data.append("login_required", loginRequired);

    // Send AJAX
    fetch(postforge_ajax.ajax_url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString()
    })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          // Clear old roles
          rolesWrapper.innerHTML = "";

          const heading = document.createElement("div");
          heading.textContent = "Select allowed roles:";
          heading.style.fontWeight = "600";
          heading.style.marginBottom = "6px";
          rolesWrapper.appendChild(heading);
          // Add inline checkboxes
          result.data.forEach(role => {
            const label = document.createElement("label");
            label.style.marginRight = "12px";

            const input = document.createElement("input");
            input.type = "checkbox";
            input.name = "postforge_allowed_roles[]";
            input.value = role.key;

            label.appendChild(input);
            label.append(" " + role.label);

            rolesWrapper.appendChild(label);
          });

          rolesWrapper.style.display = "block";
        } else {
          console.error("❌ Error:", result.data);
        }
      })
      .catch(err => console.error("Fetch error:", err));
  };

  // Trigger when post type changes OR checkbox changes
  postTypeSelect.addEventListener("change", sendData);
  checkbox.addEventListener("change", sendData);
});


/* ---------- helpers ---------- */

/** Safe HTML escape for values inserted into markup */
function escapeHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Normalize choices into an array of { value, label }.
 * Accepts:
 *  - Array of strings ['a','b']
 *  - Array of objects [{value:'a',label:'A'}]
 *  - Object map { key: 'Label' }
 *  - String "a\nb" or "a,b"
 */
function normalizeChoices(raw) {
  if (!raw && raw !== 0) return [];

  // string -> split by newline or comma
  if (typeof raw === 'string') {
    return raw
      .split(/\r?\n|,/)
      .map(s => s.trim())
      .filter(Boolean)
      .map(v => ({ value: v, label: v }));
  }

  // array -> map items
  if (Array.isArray(raw)) {
    return raw
      .map(item => {
        if (item == null) return null;

        if (typeof item === 'string' || typeof item === 'number') {
          return { value: String(item), label: String(item) };
        }

        if (typeof item === 'object') {
          // If object has explicit value/label
          if ('value' in item || 'label' in item) {
            return {
              value: item.value != null ? String(item.value) : String(item.label || ''),
              label: item.label != null ? String(item.label) : String(item.value || '')
            };
          }

          // Fallback: first key -> value
          const keys = Object.keys(item);
          if (keys.length) {
            const k = keys[0];
            return { value: k, label: item[k] != null ? String(item[k]) : String(k) };
          }
        }

        return null;
      })
      .filter(Boolean);
  }

  // object map -> entries
  if (typeof raw === 'object') {
    return Object.keys(raw).map(k => ({ value: k, label: raw[k] != null ? String(raw[k]) : String(k) }));
  }

  // fallback
  return [];
}

/**
 * Normalize saved value(s) depending on type.
 * - For checkbox: returns array of values
 * - For others: returns single string value
 */
function normalizeSavedValue(savedValue, type) {
  if (type === 'checkbox') {
    if (!savedValue) return [];
    if (Array.isArray(savedValue)) return savedValue.map(String);
    if (typeof savedValue === 'object') {
      // e.g., { optionA: "1", optionB: "" } -> choose truthy keys
      try {
        return Object.keys(savedValue).filter(k => savedValue[k]).map(String);
      } catch (e) {
        // fallback below
      }
    }
    // string -> split by newline/comma
    return String(savedValue).split(/\r?\n|,/).map(s => s.trim()).filter(Boolean);
  } else {
    if (savedValue == null) return '';
    if (Array.isArray(savedValue)) return String(savedValue[0] || '');
    if (typeof savedValue === 'object') {
      if ('value' in savedValue) return String(savedValue.value);
      const k = Object.keys(savedValue)[0];
      return k ? String(savedValue[k]) : '';
    }
    return String(savedValue);
  }
}

/* ---------- field renderer ---------- */

/**
 * Render an input UI based on field.type (text/email/number/select/checkbox)
 * - field: object from response.data.meta_keys (must contain meta_key, type, placeholder, choices, etc)
 * - saved: object for that field from savedCustomFields (may contain .value, .required, etc)
 */
function renderInputField(field, saved) {
  const type = (field.type || 'text').toLowerCase();
  const nameBase = `postforge_custom_fields[${escapeHtml(field.meta_key)}]`;
  const placeholderAttr = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
  const requiredAttr = (saved && (saved.required || saved.required === '1')) || field.required ? ' required' : '';

  const savedValue = normalizeSavedValue(saved && saved.value, type);
  const choices = normalizeChoices(field.choices);

  switch (type) {
    case 'email':
    case 'number':
    case 'text':
      return `<input type="${escapeHtml(type)}" name="${nameBase}[value]" value="${escapeHtml(savedValue)}"${placeholderAttr}${requiredAttr}>`;

    case 'select':
      // If no choices, render a text fallback
      if (!choices.length) {
        return `<input type="text" name="${nameBase}[value]" value="${escapeHtml(savedValue)}"${placeholderAttr}${requiredAttr}>`;
      }
      return `<select name="${nameBase}[value]"${requiredAttr}>
        ${choices.map(ch => {
        const v = escapeHtml(ch.value);
        const l = escapeHtml(ch.label);
        const sel = String(savedValue) === String(ch.value) ? ' selected' : '';
        return `<option value="${v}"${sel}>${l}</option>`;
      }).join('')}
      </select>`;

    case 'checkbox':
      // multiple checkboxes (choices required)
      if (!choices.length) {
        // If no choices, use a single checkbox toggle
        const checked = (Array.isArray(savedValue) ? savedValue.length > 0 : Boolean(savedValue)) ? ' checked' : '';
        return `<label><input type="checkbox" name="${nameBase}[value]" value="1"${checked}> ${escapeHtml(field.label || field.meta_key)}</label>`;
      }
      // savedValue -> array
      const savedArr = Array.isArray(savedValue) ? savedValue.map(String) : [String(savedValue)];
      return choices.map(ch => {
        const v = escapeHtml(ch.value);
        const l = escapeHtml(ch.label);
        const isChecked = savedArr.indexOf(String(ch.value)) !== -1 ? ' checked' : '';
        return `<label style="display:inline-block; margin-right:8px;">
          <input type="checkbox" name="${nameBase}[value][]" value="${v}"${isChecked}> ${l}
        </label>`;
      }).join('');

    default:
      // fallback to text
      return `<input type="text" name="${nameBase}[value]" value="${escapeHtml(savedValue)}"${placeholderAttr}${requiredAttr}>`;
  }
}

/* ---------- rows renderer ---------- */

/**
 * Render the settings table rows for a field group (enabled, label, required, and actual field UI)
 */
function renderFieldRows(field, saved, index) {
  const nameBaseEsc = escapeHtml(field.meta_key);
  const enabled = saved && saved.enabled ? 'checked' : '';
  const type = field && field.type ? field.type : 'text';
  const labelVal = saved && saved.label != null ? saved.label : field.label || '';
  const order = saved && saved.order ? saved.order : index + 1;

  // const fieldHtml = renderInputField(field, saved || {});

  return `
    <table class="form-table">
      <tr>
        <th>Enabled</th>
        <td>
          <input type="checkbox"
            name="postforge_custom_fields[${nameBaseEsc}][enabled]"
            value="1" ${enabled}>
        </td>
      </tr>
      <tr>
        <th>Label Override</th>
        <td>
          <input type="text"
            name="postforge_custom_fields[${nameBaseEsc}][label]"
            value="${escapeHtml(labelVal)}">
        </td>
      </tr>
      <tr>
        <th>Required</th>
        <td>
          <input type="checkbox"
            name="postforge_custom_fields[${nameBaseEsc}][required]"
            value="1" ${saved && saved.required ? 'checked' : ''}>
        </td>
      </tr>
      <tr>
        <th>Field Type </th>
        <td> ${escapeHtml(type)}</td>
      </tr>
    </table>
    <input type="hidden" name="postforge_custom_fields[${nameBaseEsc}][type]" value="${escapeHtml(type)}">
    <input type="hidden" class="field-order" name="postforge_custom_fields[${nameBaseEsc}][order]" value="${escapeHtml(order)}">
  `;
}