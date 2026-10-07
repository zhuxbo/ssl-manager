const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');

function setup(responses) {
  const calls = [];
  const elements = new Map();
  const document = {
    getElementById(id) {
      if (!elements.has(id)) elements.set(id, {
        style: {}, textContent: '', classList: { add() {}, remove() {} }
      });
      return elements.get(id);
    },
    querySelectorAll() { return []; }
  };
  const window = { appState: {
    cert: { dcv: { ca: 'sectigo' } },
    validation: { domain: '*.example.com', method: 'txt' }
  } };
  const context = vm.createContext({
    window, document, AbortSignal,
    Config: { getDnsToolsHosts: () => ['https://one', 'https://two', ''] },
    fetch: async (url, options) => {
      calls.push({ url, body: JSON.parse(options.body) });
      const response = responses.shift();
      if (response instanceof Error) throw response;
      return { ok: true, json: async () => response };
    },
    setLoading() {}, showToast() {}, getDelegationPrefix: () => '_acme-challenge'
  });
  vm.runInContext(fs.readFileSync(path.join(root, 'js/api.js'), 'utf8'), context);
  return { api: window.API, calls, document, window, context };
}

function diagnosis(state, overrides = {}) {
  return {
    state, dnskey: { status: 'present' }, ds: { status: 'present' },
    match: 'matched', validation: 'validated', reason: 'validated', ...overrides
  };
}

function packet(dnssec, valid = true) {
  const result = {
    domain: '*.example.com', valid, dnssec,
    caa_records: valid ? null : [{ tag: 'issue', value: 'pki.goog' }]
  };
  return valid ? { code: 1, data: { results: [result] } } : { code: 0, errors: [result] };
}

const verify = api => api.verifyIssuance('*.example.com', 'sectigo');

test('未启用隐藏，有效显示成功，通配符原样传递且不追加重复查询', async () => {
  const { api, calls } = setup([
    packet(diagnosis('insecure', { dnskey: { status: 'absent' }, ds: { status: 'absent' }, match: 'not_applicable', validation: 'not_applicable', reason: 'not_enabled' })),
    packet(diagnosis('secure'))
  ]);
  assert.equal((await verify(api)).dnssec, null);
  const result = await verify(api);
  assert.equal(result.dnssec.state, 'secure');
  assert.equal(result.dnssec.message, '有效 DNSSEC');
  assert.equal(calls.length, 2);
  assert.equal(calls[0].body.domains, '*.example.com');
});

test('配置错误保留原因和针对性建议，CAA 独立提示', async () => {
  const action = '请在 DNS 托管商处关闭签名配置（移除 DNSKEY）';
  const { api } = setup([packet(diagnosis('bogus', {
    ds: { status: 'absent' }, match: 'not_applicable', validation: 'unknown',
    reason: 'missing_ds', message: 'DNSSEC 配置错误：存在 DNSKEY，但父区缺少 DS', action
  }), false)]);
  const result = await verify(api);
  assert.equal(result.dnssec.state, 'bogus');
  assert.ok(result.dnssec.message.includes(action));
  assert.match(result.dnssec.message, /父区缺少 DS/);
  assert.match(result.caa, /sectigo/);
  assert.match(result.caa, /删除已有 CAA/);
});

test('记录匹配但信任链未确认时不显示有效或删除建议', async () => {
  const { api } = setup([packet(diagnosis('indeterminate', {
    validation: 'unknown', reason: 'validation_unconfirmed',
    message: '记录匹配，但信任链尚未通过验证', action: '不应显示的删除建议'
  }))]);
  const result = await verify(api);
  assert.equal(result.dnssec.state, 'indeterminate');
  assert.match(result.dnssec.message, /信任链尚未通过验证/);
  assert.doesNotMatch(result.dnssec.message, /删除|有效 DNSSEC/);
});

test('请求失败时切换节点，服务失败或无效响应显示无法判定', async () => {
  const first = setup([new Error('offline'), packet(diagnosis('secure'))]);
  assert.equal((await verify(first.api)).dnssec.state, 'secure');
  assert.equal(first.calls.length, 2);
  for (const responses of [[new Error('offline'), null], [packet(null), packet(null)]]) {
    const { api } = setup(responses);
    const result = await verify(api);
    assert.equal(result.dnssec.state, 'indeterminate');
    assert.match(result.dnssec.message, /检测服务不可用/);
  }
});

test('IP 跳过，缺少 CA 提示无法检测', async () => {
  const { api, calls } = setup([]);
  for (const domain of ['192.0.2.1', '2001:db8::1']) {
    assert.equal((await api.verifyIssuance(domain, 'sectigo')).dnssec, null);
  }
  assert.match((await api.verifyIssuance('example.com', '')).dnssec.message, /缺少证书 CA/);
  assert.equal(calls.length, 0);
});

test('弹窗颜色与状态对应，重检清除提示，保持 DCV 状态和 TXT 冲突独立', async () => {
  const { api, document, window, context } = setup([
    packet(diagnosis('bogus', { reason: 'ds_mismatch', match: 'mismatched', validation: 'unknown', action: '删除 DS' })),
    packet(diagnosis('secure')),
    packet(diagnosis('indeterminate', { validation: 'unknown', reason: 'validation_unconfirmed' })),
    packet(diagnosis('insecure', { dnskey: { status: 'absent' }, ds: { status: 'absent' }, match: 'not_applicable', validation: 'not_applicable', reason: 'not_enabled' }))
  ]);
  // 执行真实弹窗与检测函数，仅替换外部依赖，避免启动页面初始化及自动轮询。
  const main = fs.readFileSync(path.join(root, 'js/main.js'), 'utf8');
  const start = main.indexOf('  function updateCheckDialog(');
  vm.runInContext(main.slice(start, main.indexOf('  // 关闭弹窗', start)), context);
  context.API = {
    verifyIssuance: api.verifyIssuance, verifyDCV: async () => ({ checked: true }),
    verifyCname: async () => ({ checked: true }), verifyDelegationTxt: async () => ({ checked: true }),
    queryTxtRecords: async () => ['conflict']
  };
  for (const color of ['danger', 'success', 'warning']) {
    await context.performVerification();
    assert.equal(document.getElementById('check-dnssec-row').style.display, 'table-row');
    assert.equal(document.getElementById('check-dnssec').style.color, `var(--${color}-color)`);
    assert.equal(window.appState.validation.checked, true);
  }
  window.appState.cert.dcv.is_delegate = true;
  await context.performVerification();
  assert.equal(document.getElementById('check-dnssec-row').style.display, 'none');
  assert.equal(document.getElementById('check-dnssec').textContent, '');
  assert.equal(document.getElementById('check-txt-conflict-row').style.display, 'table-row');
});
