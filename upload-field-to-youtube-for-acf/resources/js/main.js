/**
 * Included when fields are rendered for editing by publishers.
 */
 ( function( $ ) {
    window.WPSPAGHETTI_UFTYFACF = window.WPSPAGHETTI_UFTYFACF || {};

	WPSPAGHETTI_UFTYFACF.Field = class {
		/**
		 * $field is a jQuery object wrapping field elements in the editor.
		 */
        constructor($field) {
			this.field = $field;
            this.postId = acf.get('post_id');
			this.postStatus = window[this.field.data('type') + '_obj'].postStatus;
			this.debug = window[this.field.data('type') + '_obj'].debug || false;

            this.init();
        }

		/**
		 * Debug logging wrapper - only logs if debug mode is enabled
		 */
		log(...args) {
			if (this.debug) {
				console.log('[YouTube Upload]', ...args);
			}
		}

		/**
		 * Error logging - always shown as it's important for debugging issues
		 */
		logError(...args) {
			console.error('[YouTube Upload]', ...args);
		}

		/**
		 * Warning logging - always shown for important warnings
		 */
		logWarn(...args) {
			console.warn('[YouTube Upload]', ...args);
		}

		/**
		 * Initialize the field components and event handlers.
		 */
		init() {
			this.wrapper = document.querySelector('.' + this.field.data('key') + '__wrapper');
			if(this.wrapper) {
				this.hiddenValueInput = this.wrapper.querySelector('.' + this.field.data('key') + '__hidden_value_input');
				this.hiddenModeInput = this.wrapper.querySelector('.' + this.field.data('key') + '__hidden_mode_input');
				this.tabs = this.wrapper.querySelector('.' + this.field.data('key') + '__tabs');
				this.fileInput = this.wrapper.querySelector('.' + this.field.data('key') + '__file_input');
				this.button = this.wrapper.querySelector('.' + this.field.data('key') + '__button');
				this.playlistSelect = this.wrapper.querySelector('.' + this.field.data('key') + '__playlist_select');
				this.videoSelect = this.wrapper.querySelector('.' + this.field.data('key') + '__video_select');
				this.responseDiv = this.wrapper.querySelector('.' + this.field.data('key') + '__response');
				this.spinner = this.wrapper.querySelector('.' + this.field.data('key') + '__spinner');

				this.activeTab = localStorage.getItem(this.field.data('key') + '__active_tab') || 0;
				this.playlistId = localStorage.getItem(this.field.data('key') + '_' + this.postId + '__playlist_id') || '';
				this.videoId = localStorage.getItem(this.field.data('key') + '_' + this.postId + '__video_id') || '';

				this.initTabs();
				this.initUpload();
				this.initSelect();
			};
		}

		/**
		 * Show success message in responseDiv
		 */
		showSuccess(message, showVideoId = false) {
			this.clearResponse();
			this.responseDiv.classList.add('notice', 'notice-success');
			let content = '<strong>' + message + '</strong>';
			if (showVideoId && this.hiddenValueInput.value) {
				content += '<br><small>' + acf._e(this.field.data('type'), 'video_id') + ': ' + this.hiddenValueInput.value + '</small>';
			}
			this.responseDiv.innerHTML = content;
		}

		/**
		 * Show error message in responseDiv
		 */
		showError(message, debugInfo = null) {
			this.clearResponse();
			this.responseDiv.classList.add('notice', 'notice-error');
			let content = '<strong>' + acf._e(this.field.data('type'), 'error_while_uploading') + '</strong>';
			
			if (this.debug && debugInfo) {
				content += '<br><small>' + debugInfo + '</small>';
			}
			
			this.responseDiv.innerHTML = content;
		}

		/**
		 * Show warning message in responseDiv
		 */
		showWarning(message) {
			this.clearResponse();
			this.responseDiv.classList.add('notice', 'notice-warning');
			this.responseDiv.innerHTML = '<strong>' + message + '</strong>';
		}

		/**
		 * Show info message in responseDiv
		 */
		showInfo(message) {
			this.clearResponse();
			this.responseDiv.classList.add('notice', 'notice-info');
			this.responseDiv.innerHTML = message;
		}

		/**
		 * Clear response message
		 */
		clearResponse() {
			this.responseDiv.className = this.field.data('key') + '__response ' + this.field.data('type') + '__response';
			this.responseDiv.innerHTML = '';
		}

		/**
		 * Enable upload interface
		 */
		enableUploadInterface() {
			if (this.button) {
				this.button.disabled = false;
			}
			if (this.fileInput) {
				this.fileInput.disabled = false;
			}
			if (this.playlistSelect) {
    		    this.playlistSelect.disabled = false;
    		}
    		if (this.videoSelect) {
    		    this.videoSelect.disabled = false;
    		}
			this.spinner.style.display = 'none';

			// Remove manual video ID input interface if present
			const manualInputContainer = this.wrapper.querySelector('.' + this.field.data('key') + '__manual_video_id_container');
			if (manualInputContainer) {
				manualInputContainer.remove();
			}
		}

		/**
		 * Disable upload interface
		 */
		disableUploadInterface() {
			if (this.button) {
				this.button.disabled = true;
			}
			if (this.fileInput) {
				this.fileInput.disabled = true;
			}
			if (this.playlistSelect) {
    		    this.playlistSelect.disabled = true;
    		}
    		if (this.videoSelect) {
    		    this.videoSelect.disabled = true;
    		}
		}

		/**
		 * Initialize jQuery UI tabs functionality.
		 */
		initTabs() {
			if(this.tabs) {
				$(this.tabs).tabs({
					active: parseInt(this.activeTab),
					activate: (event, ui) => {
						localStorage.setItem(this.field.data('key') + '__active_tab', ui.newTab.index());
					},
				});
			}
		}

		/**
		 * Initialize upload functionality and button event handler.
		 */
		initUpload() {
			if(this.button) {
				this.button.addEventListener('click', (event) => {
					event.preventDefault();

					let title = '';
					let excerpt = '';
					const file = this.fileInput && this.fileInput.files.length > 0 ? this.fileInput.files[0] : null;

					const isGutenberg = !!window.wp && !!window.wp.data;

					if (isGutenberg) {
						//https://wordpress.stackexchange.com/a/351788/99214
						const { select } = wp.data;
						title = select('core/editor').getEditedPostAttribute('title') || '';
						excerpt = select('core/editor').getEditedPostAttribute('excerpt') || '';
					} else {
						title = document.getElementById('title') ? document.getElementById('title').value.trim() : '';
						excerpt = document.getElementById('excerpt') ? document.getElementById('excerpt').value.trim() : '';
					}

					if (!title /*|| !excerpt*/ || !file) {
						let message = acf._e(this.field.data('type'), 'before_uploading');
						if (!title) message += '\n- ' + acf._e(this.field.data('type'), 'enter_title');
						//if (!excerpt) message += '\n- ' + acf._e(this.field.data('type'), 'enter_description');
						if (!file) message += '\n- ' + acf._e(this.field.data('type'), 'select_video_file');
						this.thickbox(message);
						return;
					}

					// Reset hidden field value and video select from second tab when starting upload
					this.resetSelectTabFields();
				
					this.showInfo(acf._e(this.field.data('type'), 'preparing_upload') + '...');
					this.disableUploadInterface();
					this.spinner.style.display = 'block';

					// Check upload method from server configuration
					const serverUpload = window[this.field.data('type') + '_obj'].serverUpload;
		
					if (serverUpload) {
						this.log('Using server-side upload method');
						this.uploadViaServer(file);
					} else {
						this.log('Using client-side upload method');
						this.uploadViaClient(file);
					}
				});
			}
		}

		/**
		 * Initialize playlist and video selection functionality.
		 */
		initSelect() {
			if(this.playlistSelect && this.videoSelect) {
				this.videoSelect.disabled = true;

				if (this.playlistId) {
					this.playlistSelect.value = this.playlistId;
				}

				this.loadVideosByPlaylist();

				this.playlistSelect.addEventListener('change', (event) => {
					this.playlistId = this.playlistSelect.value;
					this.videoId = '';

					this.hiddenValueInput.value = this.videoId;
					this.hiddenModeInput.value = '';
					this.videoSelect.innerHTML = '';
					this.videoSelect.disabled = true;

					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__playlist_id', this.playlistId);
					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__video_id', this.videoId);

					this.loadVideosByPlaylist();
				});

				this.videoSelect.addEventListener('change', (event) => {
					this.videoId = this.videoSelect.value;

					this.hiddenValueInput.value = this.videoId;

					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__video_id', this.videoId);
				});
			}
		}

		/**
		 * Reset fields from the select tab when starting an upload
		 */
		resetSelectTabFields() {
			// Reset the hidden value input
			if (this.hiddenValueInput) {
				this.hiddenValueInput.value = '';
			}

			// Reset the video select in the second tab
			if (this.videoSelect) {
				this.videoSelect.innerHTML = '';
				this.videoSelect.disabled = true;
			}

			// Clear video ID from localStorage but keep playlist ID
			this.videoId = '';
			localStorage.setItem(this.field.data('key') + '_' + this.postId + '__video_id', this.videoId);

			this.log('Reset select tab fields for new upload');
		}

		/**
		 * Load videos from the selected playlist.
		 */
		loadVideosByPlaylist() {
			if (this.playlistId) {
				this.showInfo(acf._e(this.field.data('type'), 'wait_please') + '...');
				this.spinner.style.display = 'block';

				const formData = new FormData();
				formData.append('action', 'get_videos_by_playlist');
				formData.append('field_key', this.field.data('key'));
				formData.append('playlist_id', this.playlistId);
				formData.append('_wpnonce', window[this.field.data('type') + '_obj']._wpnonce);

				fetch(ajaxurl, {
					method: 'POST',
					body: formData,
				})
					.then(response => {
						const contentType = response.headers.get("content-type");
						if (contentType && contentType.indexOf("application/json") !== -1) {
							return response.json();
						} else {
							return response.text().then(text => ({
								success: false,
								data: {
									code: response.status,
									message: this.getResponseMessage(text)
								}
							}));
						}
					})
					.then(response => {
						const message = this.getResponseMessage(response);
						if (!response.success) {
							throw new Error(message);
						}

						let defaultOption = document.createElement('option');
						defaultOption.value = '';
						defaultOption.text = '- ' + acf._e(this.field.data('type'), 'select') + ' -';
						this.videoSelect.appendChild(defaultOption);

						response.data.items.forEach(item => {
							let option = document.createElement('option');
							option.value = item.id;
							option.text = item.title + ' (' + item.id + ')';
							this.videoSelect.appendChild(option);
						});

						if (this.videoId) {
							this.videoSelect.value = this.videoId;
							this.hiddenValueInput.value = this.videoId;
						}

						this.clearResponse();
						this.videoSelect.disabled = false;
					})
					.catch(error => {
						this.logError('Error loading playlist videos:', error);
						this.showError(acf._e(this.field.data('type'), 'technical_problem'), this.getResponseMessage(error));
					})
					.finally(() => {
						this.spinner.style.display = 'none';
					});
			}
		}

		/**
		 * Client-side upload: Browser -> YouTube directly
		 */
		uploadViaClient(file) {
		    const formData = new FormData();
		    formData.append('action', 'get_youtube_upload_url');
		    formData.append('post_id', this.postId);
		    formData.append('field_key', this.field.data('key'));
		    formData.append('_wpnonce', window[this.field.data('type') + '_obj']._wpnonce);
		
		    fetch(ajaxurl, {
		        method: 'POST',
		        body: formData,
		    })
		    .then(response => response.json())
		    .then(response => {
		        if (!response.success) {
		            throw new Error(this.getResponseMessage(response));
		        }
		        this.uploadResumable(file, response.data.upload_url);
		    })
		    .catch(error => {
		        this.logError('Error getting upload URL:', error);
		        this.showError(acf._e(this.field.data('type'), 'technical_problem'), this.getResponseMessage(error));
		        this.enableUploadInterface();
		    });
		}

		/**
		 * Server-side upload: Browser -> WordPress -> YouTube
		 */
		uploadViaServer(file) {
		    const formData = new FormData();
		    formData.append('action', 'upload_video_to_youtube');
		    formData.append('post_id', this.postId);
		    formData.append('field_key', this.field.data('key'));
		    formData.append('video_file', file);
		    formData.append('_wpnonce', window[this.field.data('type') + '_obj']._wpnonce);
		
		    const xhr = new XMLHttpRequest();
		
		    // Progress tracking
		    xhr.upload.addEventListener('progress', (event) => {
		        if (event.lengthComputable) {
		            const percentComplete = (event.loaded / event.total) * 100;
		            this.showInfo(acf._e(this.field.data('type'), 'loading') + ': ' + Math.round(percentComplete) + '%');
		        }
		    }, false);
		
		    xhr.addEventListener('load', () => {
		        this.spinner.style.display = 'none';
			
		        if (xhr.status >= 200 && xhr.status < 300) {
		            try {
		                const response = JSON.parse(xhr.responseText);
		                if (response.success && response.data && response.data.video_id) {
							// Check if field was already saved server-side
							const fieldAlreadySaved = response.data.field_saved || false;
							this.handleUploadSuccess(response.data.video_id, fieldAlreadySaved);
		                } else {
		                    const errorMessage = response.data && response.data.message 
		                        ? response.data.message 
		                        : acf._e(this.field.data('type'), 'upload_failed');
		                    this.handleUploadError(errorMessage);
		                }
		            } catch (e) {
		                this.logError('Server upload parse error:', e);
		                this.handleUploadError(acf._e(this.field.data('type'), 'parse_error'));
		            }
		        } else {
		            this.handleUploadError(acf._e(this.field.data('type'), 'status_error') + ': ' + xhr.status);
		        }
		    });
		
		    xhr.addEventListener('error', () => {
		        this.logError('Server upload network error');
		        this.handleUploadError(acf._e(this.field.data('type'), 'network_error'));
		    });
		
		    this.log('Starting server upload:', {
		        fileSize: file.size,
		        fileType: file.type,
		        postId: this.postId
		    });
		
		    xhr.open('POST', ajaxurl, true);
		    xhr.send(formData);
		}

		/**
		 * Fixed upload method that handles YouTube's response correctly
		 */
		uploadResumable(file, uploadUrl) {
			const xhr = new XMLHttpRequest();
		    let uploadCompleted = false;
			
			// Progress tracking
			xhr.upload.addEventListener('progress', (event) => {
				if (event.lengthComputable) {
					const percentComplete = (event.loaded / event.total) * 100;
					this.showInfo(acf._e(this.field.data('type'), 'loading') + ': ' + Math.round(percentComplete) + '%');
		            
		            // If we reach 100%, consider it uploaded
		            if (percentComplete >= 100) {
		                uploadCompleted = true;
		            }
				}
			}, false);
		
			xhr.addEventListener('load', () => {
		        this.log('Upload load event - Status:', xhr.status);
		        this.log('Response length:', xhr.responseText ? xhr.responseText.length : 0);
				
				if (xhr.status >= 200 && xhr.status < 300) {
					try {
						const response = JSON.parse(xhr.responseText);
						if (response.id) {
		                    this.handleUploadSuccess(response.id);
		                    return;
						} else {
		                    this.logWarn('No video ID in response:', response);
						}
					} catch (e) {
		                this.log('Could not parse response, but status is OK. Checking if upload completed...');
		                
		                // If upload reached 100% and status is OK, try to extract video ID from URL
		                if (uploadCompleted && xhr.status === 200) {
		                    this.handleUploadCompletedWithoutResponse(uploadUrl);
		                    return;
					}
				}
		        }
		        
		        this.handleUploadError(acf._e(this.field.data('type'), 'status_error') + ': ' + xhr.status);
			});
		
			xhr.addEventListener('error', () => {
		        this.log('Upload error event triggered');
		        this.log('Upload completed:', uploadCompleted);
		        this.log('Status:', xhr.status);
		        
		        // YouTube often triggers 'error' event even on successful uploads due to CORS
		        if (uploadCompleted) {
		            this.log('Upload appears to have completed successfully despite error event');
		            this.handleUploadCompletedWithoutResponse(uploadUrl);
		        } else {
		            this.logError('Genuine network error during upload');
		            this.handleUploadError(acf._e(this.field.data('type'), 'network_error'));
		        }
			});

			xhr.addEventListener('abort', () => {
		        this.logWarn('Upload aborted');
		        this.handleUploadError(acf._e(this.field.data('type'), 'upload_aborted'));
			});

		    this.log('Starting upload:', {
				fileSize: file.size,
				fileType: file.type,
				uploadUrl: uploadUrl.substring(0, 50) + '...'
			});
			
		    xhr.open('PUT', uploadUrl, true);
		    xhr.setRequestHeader('Content-Type', file.type || 'video/mp4');
			xhr.send(file);
		}

		/**
		 * Handle successful upload
		 */
		handleUploadSuccess(videoId, fieldAlreadySaved = false) {
		    this.log('Upload successful, video ID:', videoId);
		    this.showSuccess(acf._e(this.field.data('type'), 'video_uploaded_successfully'), true);
		    this.hiddenValueInput.value = videoId;
		    this.hiddenModeInput.value = 'upload';
			this.spinner.style.display = 'none';
			
			// Disable upload components to prevent re-upload
			this.disableUploadInterface();
			
			// Only call save if field wasn't already saved server-side
			if (!fieldAlreadySaved) {
		    	this.save(videoId);
			} else {
				this.log('Field already saved server-side, skipping additional AJAX call');
			}
		}

		/**
		 * Handle upload that completed but without readable response
		 */
		handleUploadCompletedWithoutResponse(uploadUrl) {
		    this.log('Upload completed without readable response, trying to get video ID...');
		    this.showInfo(acf._e(this.field.data('type'), 'video_uploaded_successfully') + ' (' + acf._e(this.field.data('type'), 'verifying') + '...)');
		    
		    // Extract upload_id from URL to try to get video info
		    const urlParams = new URLSearchParams(uploadUrl.split('?')[1]);
		    const uploadId = urlParams.get('upload_id');
		    
		    if (uploadId) {
		        // Try to get the video ID through our backend
		        this.getVideoIdFromUpload(uploadId);
		    } else {
		        // Fallback: inform user to save the post
		        this.showUploadCompletedMessage();
		    }
		}

		/**
		 * Handle upload errors
		 */
		handleUploadError(errorMessage) {
			this.logError('Upload error:', errorMessage);
			this.showError(acf._e(this.field.data('type'), 'error_while_uploading'), errorMessage);
			this.enableUploadInterface();
		}

		/**
		 * Get video ID from upload ID via backend
		 */
		getVideoIdFromUpload(uploadId) {
		    const formData = new FormData();
		    formData.append('action', 'get_video_id_from_upload');
		    formData.append('upload_id', uploadId);
		    formData.append('post_id', this.postId);
		    formData.append('field_key', this.field.data('key'));
		    formData.append('_wpnonce', window[this.field.data('type') + '_obj']._wpnonce);

		    fetch(ajaxurl, {
		        method: 'POST',
		        body: formData,
		    })
		    .then(response => response.json())
		    .then(response => {
		        if (response.success && response.data.video_id) {
					// Field is already saved by the backend method, so pass true
					this.handleUploadSuccess(response.data.video_id, true);
		        } else {
		            this.log('Could not retrieve video ID from backend, showing manual entry interface');
		            this.showUploadCompletedMessage();
		        }
		    })
		    .catch((error) => {
		        this.logError('Error getting video ID from upload:', error);
		        this.showUploadCompletedMessage();
		    });
		}

		/**
		 * Show message for completed upload without video ID
		 */
		showUploadCompletedMessage() {
			this.showWarning(acf._e(this.field.data('type'), 'upload_successful_id_needed'));
			this.spinner.style.display = 'none';

			// Create manual video ID input interface
			this.createManualVideoIdInput();
			
			let message = acf._e(this.field.data('type'), 'video_uploaded_id_retrieval_failed') + '.';
    		message += '\n\n' + acf._e(this.field.data('type'), 'manual_video_id_instructions') + '.';
    		message += '\n\n' + acf._e(this.field.data('type'), 'do_not_save_before_entering_id') + '.';
			this.thickbox(message);
		}

		/**
		 * Create interface for manual video ID input
		 */
		createManualVideoIdInput() {
			// Remove existing manual input if present
			const existingManualInput = this.wrapper.querySelector('.' + this.field.data('key') + '__manual_video_id_container');
			if (existingManualInput) {
				existingManualInput.remove();
			}

			// Create container using WordPress postbox style
			const manualInputContainer = document.createElement('div');
			manualInputContainer.className = this.field.data('key') + '__manual_video_id_container postbox';

			// Create inner container
			const inside = document.createElement('div');
			inside.className = 'inside';

			// Create title
			const title = document.createElement('h3');
			title.textContent = acf._e(this.field.data('type'), 'enter_video_id_manually');

			// Create form table structure
			const table = document.createElement('table');
			table.className = 'form-table';
			
			const tbody = document.createElement('tbody');
			const tr = document.createElement('tr');
			
			// Create label cell
			const th = document.createElement('th');
			th.scope = 'row';
			const label = document.createElement('label');
			label.textContent = acf._e(this.field.data('type'), 'video_id') + ':';
			th.appendChild(label);
			
			// Create input cell
			const td = document.createElement('td');
			
			// Create input field
			const input = document.createElement('input');
			input.type = 'text';
			input.className = this.field.data('key') + '__manual_video_id_input regular-text';
			input.placeholder = acf._e(this.field.data('type'), 'video_id_placeholder');

			// Create help text
			const helpText = document.createElement('p');
			helpText.className = 'description';
			helpText.innerHTML = acf._e(this.field.data('type'), 'video_id_help_text_part1') + '.<br>' + acf._e(this.field.data('type'), 'video_id_help_text_part2') + '.';

			// Create submit paragraph wrapper
			const submitParagraph = document.createElement('p');
			submitParagraph.className = 'submit';

			// Create confirm button
			const confirmButton = document.createElement('button');
			confirmButton.type = 'button';
			confirmButton.className = 'button button-primary';
			confirmButton.textContent = acf._e(this.field.data('type'), 'confirm_video_id');
			
			// Add event listener to confirm button
			confirmButton.addEventListener('click', (event) => {
				event.preventDefault();
				const videoId = input.value.trim();
				if (videoId) {
					this.handleManualVideoIdEntry(videoId);
				} else {
					this.thickbox(acf._e(this.field.data('type'), 'please_enter_valid_video_id'));
				}
			});

			// Add enter key support to input
			input.addEventListener('keypress', (event) => {
				if (event.key === 'Enter') {
					confirmButton.click();
				}
			});

			// Assemble the structure
			td.appendChild(input);
			td.appendChild(helpText);
			
			// Add button inside submit paragraph
			submitParagraph.appendChild(confirmButton);
			td.appendChild(submitParagraph);
			
			tr.appendChild(th);
			tr.appendChild(td);
			tbody.appendChild(tr);
			table.appendChild(tbody);
			
			inside.appendChild(title);
			inside.appendChild(table);
			manualInputContainer.appendChild(inside);

			// Insert after the response div
			this.responseDiv.parentNode.insertBefore(manualInputContainer, this.responseDiv.nextSibling);
		}

		/**
		 * Handle manual video ID entry
		 */
		handleManualVideoIdEntry(videoId) {
			this.log('Manual video ID entered:', videoId);
			
			// Set the video ID in the hidden field
			this.hiddenValueInput.value = videoId;
			this.hiddenModeInput.value = 'upload';
			
			// Remove the manual input interface
			const manualInputContainer = this.wrapper.querySelector('.' + this.field.data('key') + '__manual_video_id_container');
			if (manualInputContainer) {
				manualInputContainer.remove();
			}
			
			// Show success message
			this.showSuccess(acf._e(this.field.data('type'), 'video_id_set_successfully'), true);
			
			// Save the video ID via AJAX
			this.save(videoId);
			
			// Show final success message
			let message = acf._e(this.field.data('type'), 'video_associated_successfully') + '!';
    		message += '\n' + acf._e(this.field.data('type'), 'now_safe_to_save_post') + '.';
			this.thickbox(message);
			
			// Disable upload interface since we now have a video
			this.disableUploadInterface();
		}

		/**
		 * Save the video ID to the ACF field via AJAX.
		 */
		save(videoId) {
			const formData = new FormData();
			formData.append('action', 'save_youtube_video_id');
			formData.append('post_id', this.postId);
			formData.append('field_key', this.field.data('key'));
			formData.append('video_id', videoId);
			formData.append('_wpnonce', window[this.field.data('type') + '_obj']._wpnonce);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData,
			})
				.then(response => {
					const contentType = response.headers.get("content-type");
					if (contentType && contentType.indexOf("application/json") !== -1) {
						return response.json();
					} else {
						return response.text().then(text => ({
							success: false,
							data: {
								code: response.status,
								message: this.getResponseMessage(text)
							}
						}));
					}
				})
				.then(response => {
					const message = this.getResponseMessage(response);
					if (!response.success) {
						throw new Error(message);
					}
					this.log('Video ID saved successfully');
				})
				.catch(error => {
					this.logError('Error saving video ID:', error);
					let message = acf._e(this.field.data('type'), 'following_error');
    	    	    message += '\n"' + this.getResponseMessage(error) + '"';
    	    	    message += '\n' + acf._e(this.field.data('type'), 'recommended_save_post');
    	    	    this.thickbox(message);
				});
		}

		/**
		 * Display a ThickBox modal with the given message.
		 */
		thickbox(message) {
			const uniqueId = 'thickbox_' + Date.now();
			const formattedMessage = message.replace(/\n/g, '<br>');
    	    const content = `
    	        <div id="${uniqueId}" style="display:none;">
    	            <p>${formattedMessage}</p>
    	        </div>
    	    `;

    	    document.body.insertAdjacentHTML('beforeend', content);

			//FIXME - https://core.trac.wordpress.org/ticket/27473
    	    //tb_show(acf._e(this.field.data('type'), 'attention'), '#TB_inline?&width=300&height=100&inlineId=youtube-upload-message');
			tb_show(acf._e(this.field.data('type'), 'attention'), `#TB_inline?inlineId=${uniqueId}`);

    	    // Set initial dimensions
    	    setTimeout(this.adjustThickboxSize, 0);

    	    // Add a listener for window resizing
    	    const resizeListener = () => {
    	        this.adjustThickboxSize();
    	    };
    	    window.addEventListener('resize', resizeListener);

    	    // Remove content and listener after ThickBox is closed
    	    const observer = new MutationObserver((mutations) => {
    	        mutations.forEach((mutation) => {
    	            if (mutation.type === 'childList' && mutation.removedNodes.length > 0) {
    	                for (let node of mutation.removedNodes) {
    	                    if (node.id === 'TB_window') {
    	                        document.getElementById(uniqueId).remove();
    	                        window.removeEventListener('resize', resizeListener);
    	                        observer.disconnect();
    	                        break;
    	                    }
    	                }
    	            }
    	        });
    	    });

    	    observer.observe(document.body, {
    	        childList: true
    	    });
    	}

		/**
		 * Adjust ThickBox modal size and position.
		 */
		adjustThickboxSize() {
			const thickboxWindow = document.getElementById('TB_window');
			if (thickboxWindow) {
				thickboxWindow.style.width = '300px';
				thickboxWindow.style.height = 'auto';
				thickboxWindow.style.marginLeft = '-150px';
				thickboxWindow.style.left = '50%'; // Center horizontally
			
				const thickboxContent = document.getElementById('TB_ajaxContent');
				if (thickboxContent) {
					thickboxContent.style.width = 'auto';
					thickboxContent.style.height = 'auto';
				}

				// Center vertically
				const windowHeight = window.innerHeight;
				const thickboxHeight = thickboxWindow.offsetHeight;
				thickboxWindow.style.top = Math.max(0, (windowHeight - thickboxHeight) / 2) + 'px';
			}
		}

		/**
		 * Extract error message from various response formats.
		 */
		getResponseMessage(data) {
			if (data && data.data && typeof data.data.message !== 'undefined') {
				return data.data.message;
			} else if (data && typeof data.message !== 'undefined') {
				return data.message;
			} else {
				return data || acf._e(this.field.data('type'), 'technical_problem');
			}
		}
	}

	if( typeof acf.add_action !== 'undefined' ) {
		/**
		 * Run initialize_field when existing fields of this type load,
		 * or when new fields are appended via repeaters or similar.
		 */
		acf.add_action( 'ready_field/type=upload_field_to_youtube_for_acf', function($field) {
            new WPSPAGHETTI_UFTYFACF.Field($field);
        });
		acf.add_action( 'append_field/type=upload_field_to_youtube_for_acf', function($field) {
            new WPSPAGHETTI_UFTYFACF.Field($field);
        });
	}
} )( jQuery );
