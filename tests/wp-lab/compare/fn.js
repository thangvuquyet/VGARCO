window.collectStyles = async (name) => {
  await new Promise(r => setTimeout(r, 900));
  const props = ['fontFamily','fontSize','fontWeight','fontStyle','lineHeight','letterSpacing','color','backgroundColor','borderTopWidth','borderBottomWidth','borderTopColor','borderBottomColor','borderRadius','marginTop','marginBottom','paddingTop','paddingBottom','paddingLeft','paddingRight','display','textTransform','textDecorationLine','opacity','boxShadow','textAlign','position','gridTemplateColumns','flexDirection','gap','minHeight','hyphens','textWrap'];
  const items = {}; const seen = new Set();
  for (const el of document.querySelectorAll('body, header *, main *, footer *')) {
    const first = String(el.className && el.className.baseVal === undefined ? el.className : '').trim().split(/\s+/)[0];
    const key = el === document.body ? 'body' : (first ? el.tagName.toLowerCase() + '.' + first : (/^(h1|h2|h3|h4|p|a|ul|ol|li|dl|dt|dd|figure|blockquote)$/i.test(el.tagName) ? el.tagName.toLowerCase() + '(' + (el.parentElement ? (el.parentElement.tagName.toLowerCase() + '.' + String(el.parentElement.className || '').trim().split(/\s+/)[0]) : '') + ')' : ''));
    if (!key || seen.has(key)) continue; seen.add(key);
    const cs = getComputedStyle(el); const o = {}; for (const p of props) o[p] = cs[p];
    const r = el.getBoundingClientRect(); o.__w = Math.round(r.width); o.__h = Math.round(r.height);
    items[key] = o;
  }
  const payload = JSON.stringify({ url: location.href, vw: innerWidth, n: Object.keys(items).length, items });
  await fetch('http://127.0.0.1:9100/' + name, { method: 'POST', mode: 'no-cors', body: payload });
  return Object.keys(items).length;
};
