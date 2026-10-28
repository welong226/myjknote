/* Shared API key helper for myjknote pages. kind: 'shift' | 'personal' */
(function (global) {
  var LABELS = { shift: '班表', personal: '票券／銀行／卡片' };

  function storageKey(kind) {
    return 'myjk_key_' + kind;
  }

  function get(kind) {
    try {
      return localStorage.getItem(storageKey(kind)) || '';
    } catch (e) {
      return '';
    }
  }

  function set(kind, value) {
    try {
      if (value) localStorage.setItem(storageKey(kind), value);
      else localStorage.removeItem(storageKey(kind));
    } catch (e) {}
  }

  function ask(kind, wrong) {
    var label = LABELS[kind] || kind;
    var msg = wrong
      ? '密碼不對，請再輸入「' + label + '」密碼：'
      : '請輸入「' + label + '」密碼（可請瀏覽器記住）：';
    var v = window.prompt(msg, '') || '';
    v = v.trim();
    if (v) set(kind, v);
    return v;
  }

  function ensure(kind) {
    var k = get(kind);
    if (!k) k = ask(kind, false);
    return k;
  }

  function withKeyHeaders(headers, key) {
    var h = {};
    if (headers) {
      if (typeof Headers !== 'undefined' && headers instanceof Headers) {
        headers.forEach(function (val, name) {
          h[name] = val;
        });
      } else {
        Object.keys(headers).forEach(function (name) {
          h[name] = headers[name];
        });
      }
    }
    if (key) h['X-Myjk-Key'] = key;
    return h;
  }

  async function apiFetch(kind, url, opts) {
    opts = opts || {};
    var key = ensure(kind);
    if (!key) {
      return new Response(JSON.stringify({ error: 'no key' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // multipart FormData: also put key in body (some hosts strip custom headers)
    if (opts.body && typeof FormData !== 'undefined' && opts.body instanceof FormData) {
      opts.body.set('key', key);
    }

    var attempt = Object.assign({}, opts, {
      headers: withKeyHeaders(opts.headers, key),
    });
    var r = await fetch(url, attempt);
    if (r.status !== 401) return r;

    key = ask(kind, true);
    if (!key) return r;
    if (opts.body && typeof FormData !== 'undefined' && opts.body instanceof FormData) {
      opts.body.set('key', key);
    }
    return fetch(
      url,
      Object.assign({}, opts, { headers: withKeyHeaders(opts.headers, key) })
    );
  }

  function keyQuery(kind) {
    var k = get(kind);
    return k ? 'key=' + encodeURIComponent(k) : '';
  }

  global.MyjkAuth = {
    get: get,
    set: set,
    ensure: ensure,
    fetch: apiFetch,
    keyQuery: keyQuery,
  };
})(window);
