jQuery(document).ready(function() {
	// Deactivate license
	var deactivateLicenseButton = document.getElementById('happyfiles_deactivate_license')

	if (deactivateLicenseButton) {
		deactivateLicenseButton.addEventListener('click', function(e) {
			e.preventDefault()

			jQuery.ajax({
				method: 'POST',
				url: happyFiles.ajaxUrl,
				data: {
					action: 'happyfiles_deactivate_license'
				},

				success: function(res) {
					if (res.data.hasOwnProperty('message')) {
						alert(res.data.message)
					}

					if (res.success) {
						location.reload()
					}
				}
			})
		})
	}
	
  // Init happyFiles in media library
  if (!document.body.classList.contains('edit-php')) {
    return
  }

  if (happyFiles.debug) {
    console.warn('pro.js')
	}

	HappyFiles.initSidebar()
})