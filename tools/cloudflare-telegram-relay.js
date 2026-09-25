/**
 * Cloudflare Worker: outbound relay to api.telegram.org.
 *
 * Shared Iranian hosts often cannot open api.telegram.org and cannot use
 * socks5://127.0.0.1. Deploy this Worker, then paste https://YOUR.workers.dev
 * into StoreLink → Telegram API relay URL. Test with getMe.
 *
 * Incoming Telegram webhooks still go to your public HTTPS WordPress URL
 * (or ngrok). Do not point the webhook at this Worker.
 */
export default {
	async fetch(request) {
		const incoming = new URL(request.url);
		const target = 'https://api.telegram.org' + incoming.pathname + incoming.search;
		const headers = new Headers(request.headers);
		headers.delete('host');

		return fetch(target, {
			method: request.method,
			headers,
			body: request.method === 'GET' || request.method === 'HEAD' ? undefined : request.body,
		});
	},
};
