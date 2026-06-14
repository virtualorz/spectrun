{{-- 共用:summary / timeline 的「specflow 分支下拉」+「同步專案資訊」AJAX。
     靠 element id(branchSel / syncBtn / syncMsg)+ data-project 運作,兩頁通用。 --}}
@push('scripts')
<script>
(function () {
  // ── specflow 分支下拉(lazy 載入 + 改選存檔)──
  var sel = document.getElementById('branchSel');
  var bmsg = document.getElementById('syncMsg');
  if (sel) {
    var pid = sel.dataset.project;
    var loaded = false;
    var load = function () {
      if (loaded) return;
      loaded = true;
      fetch('{{ url('repository') }}/' + pid + '/branches', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.branches) return;
          sel.innerHTML = '';
          d.branches.forEach(function (b) {
            var o = document.createElement('option');
            o.value = b; o.textContent = b;
            if (b === d.current) o.selected = true;
            sel.appendChild(o);
          });
        }).catch(function () { loaded = false; });
    };
    sel.addEventListener('focus', load);
    sel.addEventListener('change', function () {
      var branch = sel.value;
      if (!branch) return;
      fetch('{{ url('repository') }}/' + pid + '/branch', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ branch: branch })
      }).then(function (r) {
        if (!r.ok) throw new Error();
        bmsg.style.display = 'block';
        bmsg.textContent = 'specflow 分支已設為 ' + branch + ',按右上同步重新拉取。';
      }).catch(function () {
        bmsg.style.display = 'block';
        bmsg.textContent = '分支設定失敗,請稍後再試。';
      });
    });
  }

  var btn = document.getElementById('syncBtn');
  var msg = document.getElementById('syncMsg');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var pid = btn.dataset.project;
    if (!pid) return;

    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = '…';
    msg.style.display = 'block';
    msg.textContent = '同步中…(從 GitHub 拉取,可能需要一些時間)';

    fetch('{{ route('repository.sync') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ project: pid })
    }).then(function (res) {
      return res.json().then(function (d) { return { ok: res.ok, status: res.status, data: d }; });
    }).then(function (r) {
      var d = r.data || {};
      if (!r.ok) {
        msg.textContent = d.error || '同步失敗,請稍後再試';
        return;
      }
      var txt = '已同步 ' + (d.changes || 0) + ' 筆 change(成功 ' + (d.ok || 0) + '、失敗 ' + (d.failed || 0) + ')';
      if (d.aborted) {
        txt += '。' + d.aborted;
      } else if ((d.changes || 0) === 0) {
        txt += '。抓到 0 筆 —— 該 repo 的 specflow/changes 可能不在預設分支、尚未推上 GitHub、或還沒有 change。';
      }
      msg.textContent = txt + ' ';
      var rb = document.createElement('button');
      rb.type = 'button';
      rb.className = 'icbtn';
      rb.style.width = 'auto';
      rb.style.padding = '0 10px';
      rb.textContent = '重新整理';
      rb.addEventListener('click', function () { location.reload(); });
      msg.appendChild(rb);
    }).catch(function () {
      msg.textContent = '同步失敗,請稍後再試';
    }).finally(function () {
      btn.disabled = false;
      btn.innerHTML = orig;
    });
  });
})();
</script>
@endpush
