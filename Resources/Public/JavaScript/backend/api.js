import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

/**
 * POSTs JSON to a MapMagic AJAX route (Configuration/Backend/AjaxRoutes.php)
 * and returns the decoded answer. Server errors come back as `{error}` with
 * a 4xx/5xx status; they are rethrown as Error with that message, so callers
 * can show them without digging in the response.
 */
export async function call(route, payload) {
  const url = TYPO3.settings.ajaxUrls[route];
  try {
    const response = await new AjaxRequest(url).post(payload, {
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
    });
    return await response.resolve();
  } catch (failure) {
    let data = null;
    try {
      data = await failure.resolve?.();
    } catch {
      // Not JSON (e.g. an HTML error page); fall through to the generic message.
    }
    const error = new Error(data?.error ?? failure?.message ?? String(failure));
    error.data = data;
    throw error;
  }
}
