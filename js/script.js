(function () {
	function toolsGpsAdminInit() {
	let dataContainer = '',
		listFileds = 0,
		lastElementContainer = '',
		idNextElement,
		keyNextElement,
		randomNumber = '',
		templateField = '',
		roleLinkCssStyles = (window.ToolsGpsAdminConfig && window.ToolsGpsAdminConfig.roleLinkCssStyles) || [],
		typeLinkCssStyles = (window.ToolsGpsAdminConfig && window.ToolsGpsAdminConfig.typeLinkCssStyles) || [],
		attributeLinkJsScripts = (window.ToolsGpsAdminConfig && window.ToolsGpsAdminConfig.attributeLinkJsScripts) || [],
		gpsScanSessid = (window.ToolsGpsAdminConfig && window.ToolsGpsAdminConfig.sessid) || '',
		gpsScanPublicOrigin = (window.ToolsGpsAdminConfig && window.ToolsGpsAdminConfig.publicOrigin) || '';

	(function initScanUrlDefault() {
		let input = document.getElementById('tools-gps-scan-url');
		if (input && !(input.value || '').trim()) {
			input.value = (gpsScanPublicOrigin || window.location.origin) + '/';
		}
	})();

	function gpsSiteOrigin() {
		return gpsScanPublicOrigin || window.location.origin;
	}

	function gpsResolveScanPath(path) {
		path = path || '/';
		if (!path.startsWith('/')) {
			path = '/' + path;
		}
		return gpsSiteOrigin() + path;
	}

	function gpsAppendScanUrl(url) {
		let input = document.getElementById('tools-gps-scan-url');
		if (!input || !url) {
			return;
		}
		let current = (input.value || '').trim();
		if (!current) {
			input.value = url;
			return;
		}
		let parts = current.split(/[\n\r,;]+/).map((p) => p.trim()).filter(Boolean);
		let needle = url.toLowerCase();
		let exists = parts.some((p) => p.toLowerCase() === needle);
		if (exists) {
			return;
		}
		input.value = current + '\n' + url;
	}

	document.getElementById('tools-gps-scan-presets')?.addEventListener('click', (event) => {
		let btn = event.target.closest('.tools-gps-scan__preset-btn');
		if (!btn) {
			return;
		}
		gpsAppendScanUrl(gpsResolveScanPath(btn.getAttribute('data-scan-path') || '/'));
	});

	function gpsCollectExistingJsParts() {
		let parts = [];
		document.querySelectorAll('[data-container="link-js"] input[name*="[STRING_PUBLIC_PART]"]').forEach((el) => {
			let v = (el.value || '').trim();
			if (v) {
				parts.push(v);
			}
		});
		return parts;
	}

	function gpsIsJsPartAlreadyInForm(publicPart) {
		let needle = (publicPart || '').toLowerCase();
		if (!needle) {
			return false;
		}
		let found = false;
		document.querySelectorAll('[data-container="link-js"] input[name*="[STRING_PUBLIC_PART]"]').forEach((el) => {
			let v = (el.value || '').trim().toLowerCase();
			if (v && (v === needle || v.indexOf(needle) !== -1 || needle.indexOf(v) !== -1)) {
				found = true;
			}
		});
		return found;
	}

	function gpsFillOrAddJsRule(publicPart, attribute) {
		publicPart = (publicPart || '').trim();
		attribute = attribute === 'async' ? 'async' : 'defer';
		if (!publicPart || gpsIsJsPartAlreadyInForm(publicPart)) {
			return false;
		}

		let rows = document.querySelectorAll('[data-container="link-js"]');
		let emptyRow = null;
		rows.forEach((row) => {
			let input = row.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
			if (input && !(input.value || '').trim() && !emptyRow) {
				emptyRow = row;
			}
		});

		if (emptyRow) {
			let input = emptyRow.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
			let select = emptyRow.querySelector('select[name*="[ATTRIBUTE]"]');
			let checkbox = emptyRow.querySelector('input[type="checkbox"][name*="[ACTIVE]"]');
			if (input) {
				input.value = publicPart;
			}
			if (select) {
				select.value = attribute;
			}
			if (checkbox) {
				checkbox.checked = true;
			}
			return true;
		}

		let addBtn = document.querySelector('[data-container-button="link-js"]');
		if (addBtn) {
			addBtn.click();
			rows = document.querySelectorAll('[data-container="link-js"]');
			let last = rows[rows.length - 1];
			if (last) {
				let input = last.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
				let select = last.querySelector('select[name*="[ATTRIBUTE]"]');
				let checkbox = last.querySelector('input[type="checkbox"][name*="[ACTIVE]"]');
				if (input) {
					input.value = publicPart;
				}
				if (select) {
					select.value = attribute;
				}
				if (checkbox) {
					checkbox.checked = true;
				}
				return true;
			}
		}
		return false;
	}

	function gpsEscapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function gpsRenderScanResults(data) {
		let statsEl = document.getElementById('tools-gps-scan-stats');
		let listEl = document.getElementById('tools-gps-scan-list');
		let errorEl = document.getElementById('tools-gps-scan-error');
		let warnEl = document.getElementById('tools-gps-scan-warn');
		if (!statsEl || !listEl || !errorEl) {
			return;
		}

		errorEl.hidden = true;
		errorEl.textContent = '';
		if (warnEl) {
			if (data.error) {
				warnEl.hidden = false;
				warnEl.textContent = data.error;
			} else {
				warnEl.hidden = true;
				warnEl.textContent = '';
			}
		}

		let s = data.stats || {};
		let scannedN = (data.scannedUrls && data.scannedUrls.length) ? data.scannedUrls.length : 0;
		statsEl.hidden = false;
		statsEl.innerHTML =
			'Страниц: <strong>' + scannedN + '</strong>' +
			' · найдено: <strong>' + (s.found || 0) + '</strong>' +
			' · в списке: <strong>' + (s.shown || 0) + '</strong>' +
			' · скрыто ядро: <strong>' + (s.hiddenCore || 0) + '</strong>' +
			' · скрыто аналитика: <strong>' + (s.hiddenAnalytics || 0) + '</strong>' +
			' · уже async/defer: <strong>' + (s.hiddenNonBlocking || 0) + '</strong>' +
			' · пресеты: <strong>' + (s.presetMatched || 0) + '</strong>' +
			' · уже в правилах: <strong>' + (s.alreadyInRules || 0) + '</strong>';

		listEl.innerHTML = '';
		let scripts = data.scripts || [];
		if (!scripts.length) {
			listEl.innerHTML = '<div class="tools-gps-scan__empty">После фильтров подходящих скриптов нет.</div>';
			return;
		}

		scripts.forEach((item) => {
			let row = document.createElement('div');
			row.className = 'tools-gps-scan__item';
			if (item.preset) {
				row.classList.add('tools-gps-scan__item--known');
				row.style.setProperty('--gps-preset-color', item.preset.color || '#0ea5e9');
			}
			if (item.alreadyInRules) {
				row.classList.add('tools-gps-scan__item--added');
			}

			let badge = '';
			if (item.preset) {
				badge = '<span class="tools-gps-scan__badge">' + gpsEscapeHtml(item.preset.label) + '</span>';
			}

			let status = item.alreadyInRules
				? '<span class="tools-gps-scan__status">уже в правилах</span>'
				: '';

			let btnLabel = item.preset
				? 'Добавить (' + gpsEscapeHtml(item.attribute || 'defer') + ')'
				: 'Добавить';
			let btnDisabled = item.alreadyInRules ? ' disabled' : '';

			row.innerHTML =
				'<div class="tools-gps-scan__item-main">' +
					badge +
					'<code class="tools-gps-scan__src" title="' + gpsEscapeHtml(item.src || '') + '">' + gpsEscapeHtml(item.publicPart || '') + '</code>' +
					status +
				'</div>' +
				'<button type="button" class="tools-gps-scan__add-btn adm-btn"' + btnDisabled +
					' data-public-part="' + gpsEscapeHtml(item.publicPart || '') + '"' +
					' data-attribute="' + gpsEscapeHtml(item.attribute || 'defer') + '">' +
					btnLabel +
				'</button>';

			listEl.appendChild(row);
		});
	}

	document.getElementById('tools-gps-scan-run')?.addEventListener('click', () => {
		let urlInput = document.getElementById('tools-gps-scan-url');
		let errorEl = document.getElementById('tools-gps-scan-error');
		let warnEl = document.getElementById('tools-gps-scan-warn');
		let btn = document.getElementById('tools-gps-scan-run');
		let pageUrl = (urlInput?.value || '').trim();
		if (!pageUrl) {
			if (errorEl) {
				errorEl.hidden = false;
				errorEl.textContent = 'Укажите URL страницы.';
			}
			if (warnEl) {
				warnEl.hidden = true;
			}
			return;
		}

		btn.disabled = true;
		btn.value = 'Сканирование…';

		let body = new FormData();
		body.append('action', 'gps_scan_scripts');
		body.append('sessid', gpsScanSessid);
		body.append('page_url', pageUrl);
		gpsCollectExistingJsParts().forEach((part) => {
			body.append('existing[]', part);
		});

		fetch(window.location.href, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		})
			.then((r) => r.json())
			.then((data) => {
				if (!data || !data.ok) {
					if (errorEl) {
						errorEl.hidden = false;
						errorEl.textContent = (data && data.error) ? data.error : 'Ошибка сканирования.';
					}
					if (warnEl) {
						warnEl.hidden = true;
					}
					document.getElementById('tools-gps-scan-stats').hidden = true;
					document.getElementById('tools-gps-scan-list').innerHTML = '';
					return;
				}

				gpsRenderScanResults(data);

				(data.scripts || []).forEach((item) => {
					if (item.autoAdd) {
						gpsFillOrAddJsRule(item.publicPart, item.attribute);
					}
				});

				gpsRenderScanResults({
					ok: true,
					error: data.error,
					stats: data.stats,
					scannedUrls: data.scannedUrls,
					scripts: (data.scripts || []).map((item) => {
						let copy = Object.assign({}, item);
						if (gpsIsJsPartAlreadyInForm(copy.publicPart)) {
							copy.alreadyInRules = true;
							copy.autoAdd = false;
						}
						return copy;
					}),
				});
			})
			.catch(() => {
				if (errorEl) {
					errorEl.hidden = false;
					errorEl.textContent = 'Сбой запроса к админке.';
				}
			})
			.finally(() => {
				btn.disabled = false;
				btn.value = 'Сканировать';
			});
	});

	document.getElementById('tools-gps-scan-list')?.addEventListener('click', (event) => {
		let btn = event.target.closest('.tools-gps-scan__add-btn');
		if (!btn || btn.disabled) {
			return;
		}
		let publicPart = btn.getAttribute('data-public-part') || '';
		let attribute = btn.getAttribute('data-attribute') || 'defer';
		if (gpsFillOrAddJsRule(publicPart, attribute)) {
			btn.disabled = true;
			btn.textContent = 'Добавлено';
			let item = btn.closest('.tools-gps-scan__item');
			if (item) {
				item.classList.add('tools-gps-scan__item--added');
				let main = item.querySelector('.tools-gps-scan__item-main');
				if (main && !main.querySelector('.tools-gps-scan__status')) {
					main.insertAdjacentHTML('beforeend', '<span class="tools-gps-scan__status">уже в правилах</span>');
				}
			}
		}
	});

	(function initGpsPsiVariance() {
		const root = document.getElementById('tools-gps-psi');
		if (!root) {
			return;
		}

		const PAUSE_MS = 45000;
		let aborted = false;
		let running = false;
		let pauseTimer = null;

		const el = {
			url: document.getElementById('tools-gps-psi-url'),
			urlFromSite: document.getElementById('tools-gps-psi-url-from-site'),
			labelPrefix: document.getElementById('tools-gps-psi-label-prefix'),
			key: document.getElementById('tools-gps-psi-api-key'),
			keyStatus: document.getElementById('tools-gps-psi-key-status'),
			saveKey: document.getElementById('tools-gps-psi-save-key'),
			deleteKey: document.getElementById('tools-gps-psi-delete-key'),
			n: document.getElementById('tools-gps-psi-n'),
			mobile: document.getElementById('tools-gps-psi-mobile'),
			desktop: document.getElementById('tools-gps-psi-desktop'),
			start: document.getElementById('tools-gps-psi-start'),
			stop: document.getElementById('tools-gps-psi-stop'),
			progress: document.getElementById('tools-gps-psi-progress'),
			progressFill: document.getElementById('tools-gps-psi-progress-fill'),
			progressText: document.getElementById('tools-gps-psi-progress-text'),
			log: document.getElementById('tools-gps-psi-log'),
			error: document.getElementById('tools-gps-psi-error'),
			runs: document.getElementById('tools-gps-psi-runs'),
			refresh: document.getElementById('tools-gps-psi-refresh'),
			deleteBtn: document.getElementById('tools-gps-psi-delete'),
			report: document.getElementById('tools-gps-psi-report'),
		};

		function psiPost(action, fields) {
			const body = new FormData();
			body.append('action', action);
			body.append('sessid', gpsScanSessid);
			Object.keys(fields || {}).forEach((key) => {
				const val = fields[key];
				if (Array.isArray(val)) {
					val.forEach((item) => body.append(key + '[]', item));
				} else if (val !== undefined && val !== null) {
					body.append(key, String(val));
				}
			});
			return fetch(window.location.href, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
			}).then((r) => r.json());
		}

		function showError(msg) {
			if (!el.error) return;
			el.error.hidden = !msg;
			el.error.textContent = msg || '';
		}

		function appendLog(line) {
			if (!el.log) return;
			el.log.hidden = false;
			el.log.textContent += (el.log.textContent ? '\n' : '') + line;
			el.log.scrollTop = el.log.scrollHeight;
		}

		function setProgress(done, total) {
			if (!el.progress) return;
			el.progress.hidden = false;
			const pct = total > 0 ? Math.round((done / total) * 100) : 0;
			if (el.progressFill) el.progressFill.style.width = pct + '%';
			if (el.progressText) el.progressText.textContent = done + ' / ' + total;
		}

		function setRunningUi(isRunning) {
			running = isRunning;
			if (el.start) el.start.disabled = isRunning;
			if (el.stop) el.stop.disabled = !isRunning;
			if (el.url) el.url.disabled = isRunning;
			if (el.urlFromSite) el.urlFromSite.disabled = isRunning;
			if (el.labelPrefix) el.labelPrefix.disabled = isRunning;
			if (el.n) el.n.disabled = isRunning;
			if (el.mobile) el.mobile.disabled = isRunning;
			if (el.desktop) el.desktop.disabled = isRunning;
		}

		let pauseResolve = null;

		function sleep(ms) {
			return new Promise((resolve) => {
				pauseResolve = resolve;
				pauseTimer = setTimeout(() => {
					pauseTimer = null;
					pauseResolve = null;
					resolve();
				}, ms);
			});
		}

		function clearPause() {
			if (pauseTimer) {
				clearTimeout(pauseTimer);
				pauseTimer = null;
			}
			if (pauseResolve) {
				const resolve = pauseResolve;
				pauseResolve = null;
				resolve();
			}
		}

		function fillRunsSelect(runs, selectId) {
			if (!el.runs) return;
			const list = Array.isArray(runs) ? runs : [];
			el.runs.innerHTML = '';
			if (!list.length) {
				el.runs.innerHTML = '<option value="">— нет сохранённых серий —</option>';
				if (el.deleteBtn) el.deleteBtn.disabled = true;
				return;
			}
			list.forEach((run) => {
				const opt = document.createElement('option');
				opt.value = run.id || '';
				opt.textContent = run.label || run.id || '';
				el.runs.appendChild(opt);
			});
			if (selectId) {
				el.runs.value = selectId;
			}
			if (el.deleteBtn) el.deleteBtn.disabled = !el.runs.value;
		}

		function showReportHtml(html) {
			if (!el.report) return;
			if (html) {
				el.report.innerHTML = html;
			} else {
				el.report.innerHTML = '<p class="tools-gps-psi-report__empty">Нет данных отчёта для этой серии.</p>';
			}
		}

		function loadRuns(selectId) {
			return psiPost('gps_psi_list_runs', {}).then((data) => {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Не удалось загрузить список серий.');
					return;
				}
				fillRunsSelect(data.runs || [], selectId || '');
				if (selectId) {
					return loadRun(selectId);
				}
			});
		}

		function loadRun(runId) {
			if (!runId) {
				showReportHtml('');
				if (el.deleteBtn) el.deleteBtn.disabled = true;
				return Promise.resolve();
			}
			if (el.deleteBtn) el.deleteBtn.disabled = false;
			return psiPost('gps_psi_get_run', { run_id: runId }).then((data) => {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Не удалось загрузить серию.');
					return;
				}
				showError('');
				showReportHtml(data.html || '');
			});
		}

		el.urlFromSite?.addEventListener('click', () => {
			psiPost('gps_psi_probe_url', {}).then((data) => {
				if (!data || !data.ok || !data.url) {
					showError((data && data.error) || 'Не удалось получить домен сайта.');
					return;
				}
				showError('');
				if (el.url) {
					el.url.value = data.url;
				}
			}).catch(() => {
				showError('Сбой запроса домена сайта.');
			});
		});

		function applyKeyUi(hasKey) {
			if (el.keyStatus) {
				el.keyStatus.hidden = !hasKey;
				el.keyStatus.textContent = hasKey ? 'ключ есть' : '';
			}
			if (el.deleteKey) {
				el.deleteKey.disabled = !hasKey;
			}
			if (el.key) {
				el.key.placeholder = hasKey
					? '•••••••• (ключ сохранён — введите новый, чтобы заменить)'
					: 'Вставьте ключ Google PageSpeed Insights API';
			}
		}

		el.saveKey?.addEventListener('click', () => {
			const key = (el.key?.value || '').trim();
			psiPost('gps_psi_save_key', { api_key: key }).then((data) => {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Не удалось сохранить ключ.');
					return;
				}
				showError('');
				if (el.key) el.key.value = '';
				applyKeyUi(!!data.hasKey);
			});
		});

		el.deleteKey?.addEventListener('click', () => {
			if (!window.confirm('Удалить сохранённый ключ API PSI?')) return;
			psiPost('gps_psi_delete_key', {}).then((data) => {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Не удалось удалить ключ.');
					return;
				}
				showError('');
				if (el.key) el.key.value = '';
				applyKeyUi(false);
			});
		});

		el.refresh?.addEventListener('click', () => {
			loadRuns(el.runs?.value || '');
		});

		el.runs?.addEventListener('change', () => {
			loadRun(el.runs.value || '');
		});

		el.deleteBtn?.addEventListener('click', () => {
			const runId = el.runs?.value || '';
			if (!runId) return;
			if (!window.confirm('Удалить выбранную серию с диска?')) return;
			psiPost('gps_psi_delete_run', { run_id: runId }).then((data) => {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Не удалось удалить серию.');
					return;
				}
				showError('');
				fillRunsSelect(data.runs || [], '');
				showReportHtml('');
			});
		});

		el.stop?.addEventListener('click', () => {
			aborted = true;
			clearPause();
			appendLog('Стоп: после текущего прогона серия будет завершена…');
		});

		el.start?.addEventListener('click', async () => {
			if (running) return;
			showError('');
			if (el.log) {
				el.log.hidden = false;
				el.log.textContent = '';
			}

			const strategies = [];
			if (el.mobile?.checked) strategies.push('mobile');
			if (el.desktop?.checked) strategies.push('desktop');
			if (!strategies.length) {
				showError('Выберите хотя бы одно устройство.');
				return;
			}

			const probeUrl = (el.url?.value || '').trim();
			if (!probeUrl) {
				showError('Укажите URL для прогона.');
				return;
			}

			aborted = false;
			setRunningUi(true);
			setProgress(0, 1);

			let startData;
			try {
				startData = await psiPost('gps_psi_start', {
					url: probeUrl,
					n: el.n?.value || 5,
					strategies: strategies,
					label_prefix: (el.labelPrefix?.value || '').trim(),
				});
			} catch (e) {
				setRunningUi(false);
				showError('Сбой запроса старта серии.');
				return;
			}

			if (!startData || !startData.ok) {
				setRunningUi(false);
				showError((startData && startData.error) || 'Не удалось стартовать серию.');
				return;
			}

			const queue = startData.queue || [];
			const total = queue.length || startData.total || 0;
			const runId = startData.runId;
			appendLog('Серия ' + runId + ' · ' + (startData.url || '') + ' · шагов: ' + total);
			setProgress(0, total);

			let done = 0;
			let failed = false;
			let failMessage = '';

			for (let i = 0; i < queue.length; i++) {
				if (aborted) {
					break;
				}
				const step = queue[i];
				appendLog('… ' + step.strategy + ' #' + step.n);
				let one;
				try {
					one = await psiPost('gps_psi_run_one', {
						run_id: runId,
						strategy: step.strategy,
						n: step.n,
					});
				} catch (e) {
					failed = true;
					failMessage = 'Сбой сети на прогоне ' + step.strategy + ' #' + step.n;
					break;
				}
				if (!one || !one.ok) {
					failed = true;
					failMessage = (one && one.error) || ('Ошибка прогона ' + step.strategy + ' #' + step.n);
					appendLog('✗ ' + failMessage);
					break;
				}
				done += 1;
				const score = one.metrics && one.metrics.score != null ? one.metrics.score : '—';
				appendLog('✓ ' + step.strategy + ' #' + step.n + ' → ' + score);
				setProgress(done, total);

				const isLast = i === queue.length - 1;
				if (!isLast && !aborted) {
					appendLog('пауза 45 с…');
					await sleep(PAUSE_MS);
				}
			}

			clearPause();
			const finalStatus = failed ? 'error' : (aborted ? 'stopped' : 'done');
			let fin;
			try {
				fin = await psiPost('gps_psi_finalize', {
					run_id: runId,
					status: finalStatus,
				});
			} catch (e) {
				setRunningUi(false);
				showError('Прогоны частично выполнены, но finalize не удался.');
				return;
			}

			setRunningUi(false);
			if (!fin || !fin.ok) {
				showError((fin && fin.error) || failMessage || 'Ошибка завершения серии.');
			} else {
				appendLog('Готово · статус: ' + finalStatus);
				if (failMessage) {
					showError(failMessage);
				}
				showReportHtml(fin.html || '');
			}
			await loadRuns(runId);
		});

		loadRuns();
	})();

	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('tools-gps-filed__add')) {
			randomNumber = Math.random();
			dataContainer = event.target.dataset.containerButton;
			listFileds = document.querySelectorAll('[data-container=' + dataContainer + ']');
			lastElementContainer = listFileds[listFileds.length - 1];
			idNextElement = Number(lastElementContainer.dataset.id) + 1;
			keyNextElement = Number(lastElementContainer.dataset.key) + 1;
			if (dataContainer == 'link-css') {
				templateField =
					`<tr class="tools-gps-filed" data-container="` + dataContainer + `" data-id="` + idNextElement + `" data-key="` + keyNextElement + `">
						<td class="tools-gps-filed__number">` + (listFileds.length + 1) + `.</td>
						<td class="tools-gps-filed__active">
							<input type="checkbox" name="STRING_PUBLIC_PART[` + keyNextElement + `][ACTIVE]" value="Y" size="60" id="designed_checkbox_` + randomNumber + `" class="adm-designed-checkbox">
							<label class="adm-designed-checkbox-label" for="designed_checkbox_` + randomNumber + `" title=""></label>
						</td>
						<td class="tools-gps-filed__value">
							<input type="hidden" name="STRING_PUBLIC_PART[` + keyNextElement + `][ID]" value="` + idNextElement + `" size="60">
						</td>
						<td class="tools-gps-filed__text">
							href=
						</td>
						<td class="tools-gps-filed__value">
							<input type="text" name="STRING_PUBLIC_PART[` + keyNextElement + `][STRING_PUBLIC_PART]" value="" size="60">
						</td>
						<td class="tools-gps-filed__text">
							rel=
						</td>
						<td class="tools-gps-filed__value">
							<select  name="STRING_PUBLIC_PART[` + keyNextElement + `][ROLE]">`;

				roleLinkCssStyles.forEach((valueRole, keyRole, array) => {
					templateField += `<option value="${valueRole}">${valueRole}</option>`
				});

				templateField +=
					`</select>
						</td>
						<td class="tools-gps-filed__text">
							as=
						</td>
						<td class="tools-gps-filed__value">
							<select  name="STRING_PUBLIC_PART[` + keyNextElement + `][TYPE]">`;

				typeLinkCssStyles.forEach((valueType, keyType, array) => {
					templateField += `<option value="${valueType}">${valueType}</option>`
				});

				templateField +=
					`</select>
						</td>
						<td class="tools-gps-filed__delete">
							<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
						</td>
					</tr>`;
			} else if (dataContainer == 'link-js') {
				templateField =
					`<tr class="tools-gps-filed" data-container="` + dataContainer + `" data-id="` + idNextElement + `" data-key="` + keyNextElement + `">
						<td class="tools-gps-filed__number">` + (listFileds.length + 1) + `.</td>
						<td class="tools-gps-filed__active">
							<input type="checkbox" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ACTIVE]" value="Y" size="60" id="designed_checkbox_` + randomNumber + `" class="adm-designed-checkbox">
							<label class="adm-designed-checkbox-label" for="designed_checkbox_` + randomNumber + `" title=""></label>
						</td>
						<td class="tools-gps-filed__value">
							<input type="hidden" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ID]" value="` + idNextElement + `" size="60">
						</td>
						<td class="tools-gps-filed__value">
							<select  name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ATTRIBUTE]">`;

				attributeLinkJsScripts.forEach((valueAttribute) => {
					templateField += `<option value="${valueAttribute}">${valueAttribute}</option>`
				});

				templateField += `</select>
						</td>
						<td class="tools-gps-filed__text">
							src=
						</td>
						<td class="tools-gps-filed__value tools-gps-filed__value--src">
							<input type="text" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][STRING_PUBLIC_PART]" value="" size="40">
						</td>
						<td class="tools-gps-filed__delete">
							<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
						</td>
					</tr>`;
			}
			lastElementContainer.insertAdjacentHTML('afterend', templateField);
		}

		if (event.target.classList.contains('tools-gps-filed__delete-field')) {
			event.target.closest('.tools-gps-filed').remove();
		}
	})

	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', toolsGpsAdminInit);
	} else {
		toolsGpsAdminInit();
	}
})();
