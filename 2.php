<?php
// index.php - Minimal PHP, JSON fetched via XHR
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
<title>KaiOS J2ME Launcher</title>
<!-- JSZip for ZIP extraction -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<style>
  body {
    margin: 0; font-family: Arial, sans-serif; background:#222; color:#fff;
    display:flex; flex-direction:column; height:100vh;
  }
  h1 {
    text-align:center; font-size:1.2em; margin:0; padding:10px; background:#333;
    text-transform:uppercase;
  }
  .controls {
    display:flex; justify-content:space-around; padding:5px; background:#444;
  }
  select, button, input {
    padding:5px; border:none; border-radius:3px; font-size:0.9em;
  }
  #search-box {
    width:90%; margin:5px auto; padding:5px; font-size:1em; border-radius:3px; border:none;
  }
  #file-list {
    flex:1; overflow-y:auto; margin:5px;
  }
  #file-list div {
    padding:10px; margin:3px 0; background:#333; border-radius:3px; cursor:pointer;
  }
  #file-list div.selected {
    background:#00aaff; color:#fff; font-weight:bold;
  }
  .pagination {
    display:flex; justify-content:space-between; align-items:center; padding:5px 10px; background:#444;
  }
  .modal {
    display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7);
    align-items:center; justify-content:center; z-index:999;
  }
  .modal .content {
    background:#222; padding:20px; border-radius:5px; text-align:center; font-size:1em;
  }
  .softkeys {
    display:flex; justify-content:space-around; padding:5px; background:#333; font-size:0.9em;
  }
</style>
</head>
<body>

<h1>KaiOS J2ME Launcher</h1>
<div class="controls">
  <select id="sort-by">
    <option value="none">Sort: None</option>
    <option value="name">Name</option>
    <option value="type">Type</option>
  </select>
  <button id="sort-order">↑</button>
  <select id="items-per-page">
    <option value="10">Show: 10</option>
    <option value="20">20</option>
    <option value="50">50</option>
  </select>
</div>
<input type="text" id="search-box" placeholder="Search..." />

<div id="file-list"></div>
<div class="pagination">
  <button id="prev">Prev</button>
  <span id="page-info">Page 1</span>
  <button id="next">Next</button>
</div>

<div id="modal" class="modal">
  <div class="content" id="modal-message">Loading...</div>
</div>

<div class="softkeys">
  <div id="soft-left">Back</div>
  <div id="soft-center">Select</div>
  <div id="soft-right">Exit</div>
</div>

<script>
  // Variables
  let allFiles = [];
  let filteredFiles = [];
  let currentPage = 1;
  let itemsPerPage = 10;
  let sortCriteria = 'none';
  let sortOrder = 'asc';

  let listIndex = -1;
  let currentFocus = 'search'; // 'search', 'list', control ids
  const controlsOrder = ['search', 'sort-by', 'sort-order', 'items-per-page'];

  // Utility
  function showModal(msg) {
    document.getElementById('modal-message').innerText = msg;
    document.getElementById('modal').style.display='flex';
  }
  function hideModal() {
    document.getElementById('modal').style.display='none';
  }

  // Fetch JSON via XHR
  function fetchFileList() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'list.json', true);
    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const lines = JSON.parse(xhr.responseText);
          allFiles = lines.map(p => {
            const name = p.substring(p.lastIndexOf('/')+1).replace(/\.jar$/i,'');
            const type = p.substring(p.lastIndexOf('.')+1);
            return {path: p, name: name, type: type};
          });
        } catch(e) {
          alert('Error parsing list.json');
        }
        applyFilters();
      } else {
        alert('Failed to load list.json');
      }
    };
    xhr.onerror = () => alert('Network error loading list.json');
    xhr.send();
  }

  function applyFilters() {
    const searchTerm = document.getElementById('search-box').value.toLowerCase();
    filteredFiles = allFiles.filter(f => f.name.toLowerCase().includes(searchTerm));
    sortFiles();
    currentPage=1;
    renderPage();
  }

  function sortFiles() {
    if (sortCriteria==='none') return;
    filteredFiles.sort((a,b) => {
      let valA=a[sortCriteria], valB=b[sortCriteria];
      if (sortCriteria==='type') { valA=a.type; valB=b.type; }
      const cmp = valA.toLowerCase().localeCompare(valB.toLowerCase());
      return (sortOrder==='asc') ? cmp : -cmp;
    });
  }

  function renderPage() {
    const container = document.getElementById('file-list');
    container.innerHTML='';
    if (filteredFiles.length===0) {
      container.innerHTML='<div>No files found</div>';
      document.getElementById('page-info').innerText='Page 1';
      return;
    }
    const start = (currentPage-1)*itemsPerPage;
    const pageItems = filteredFiles.slice(start, start+itemsPerPage);
    pageItems.forEach((f,i) => {
      const div = document.createElement('div');
      div.innerText=f.name;
      div.setAttribute('data-index', start+i);
      div.onclick=()=> handleFileClick(f.path);
      container.appendChild(div);
    });
    document.getElementById('page-info').innerText=`Page ${currentPage}`;
    updateSelection();
  }

  function changePage(delta) {
    const totalPages = Math.ceil(filteredFiles.length/itemsPerPage);
    if (delta<0 && currentPage>1) currentPage--;
    if (delta>0 && currentPage<totalPages) currentPage++;
    renderPage();
  }

  function handleFileClick(path) {
    showModal('Extracting...');
    extractMidletName(path, (err, name) => {
      hideModal();
      if (err) {
        alert(err.message);
      } else {
        alert('MIDlet: ' + name);
        // You can handle launching or redirect here
      }
    });
  }

  function extractMidletName(zipUrl, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', zipUrl, true);
    xhr.responseType='arraybuffer';
    xhr.onload= ()=> {
      if (xhr.status===200) {
        JSZip.loadAsync(xhr.response).then(zip => {
          return zip.file('META-INF/MANIFEST.MF')?.async('string');
        }).then(content => {
          if (!content) throw new Error('MANIFEST.MF not found');
          const lines = content.split(/\r?\n/);
          const midletLine = lines.find(l=>l.match(/^MIDlet-\d+:/));
          if (!midletLine) throw new Error('MIDlet line not found');
          const parts = midletLine.split(':')[1].split(',');
          callback(null, parts[parts.length-1].trim());
        }).catch(err => callback(err, null));
      } else {
        callback(new Error('Failed to fetch ZIP'), null);
      }
    };
    xhr.onerror= ()=> callback(new Error('Network error'), null);
    xhr.send();
  }

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (e.key==='ArrowUp') {
      if (currentFocus==='list') {
        if (listIndex>0) listIndex--;
      } else {
        const idx=controlsOrder.indexOf(currentFocus);
        if (idx>0) currentFocus=controlsOrder[idx-1];
        else if (filteredFiles.length>0) {
          currentFocus='list'; listIndex=0;
        }
      }
      updateSelection();
    } else if (e.key==='ArrowDown') {
      if (currentFocus==='list') {
        if (listIndex<document.querySelectorAll('#file-list div').length-1) listIndex++;
      } else {
        const idx=controlsOrder.indexOf(currentFocus);
        if (idx<controlsOrder.length-1) currentFocus=controlsOrder[idx+1];
      }
      updateSelection();
    } else if (e.key==='ArrowLeft') {
      if (currentFocus==='list') changePage(-1);
    } else if (e.key==='ArrowRight') {
      if (currentFocus==='list') changePage(1);
    } else if (e.key==='Enter') {
      if (currentFocus==='list' && listIndex>-1) {
        const itemDivs = document.querySelectorAll('#file-list div');
        if (itemDivs[listIndex]) itemDivs[listIndex].click();
      } else if (currentFocus==='sort-order') {
        toggleSortOrder();
      }
    } else if (e.key==='Backspace') {
      window.close();
    }
  });

  function updateSelection() {
    document.querySelectorAll('#file-list div').forEach((d,i)=>
      d.classList.toggle('selected', i===listIndex));
    if (currentFocus==='search') {
      document.getElementById('search-box').focus();
    }
  }

  function toggleSortOrder() {
    sortOrder= (sortOrder==='asc')?'desc':'asc';
    document.getElementById('sort-order').innerText= (sortOrder==='asc')?'↑':'↓';
    applyFilters();
  }

  // Event handlers
  document.getElementById('sort-by').onchange=()=>{ sortCriteria=document.getElementById('sort-by').value; applyFilters(); };
  document.getElementById('sort-order').onclick=toggleSortOrder;
  document.getElementById('items-per-page').onchange=()=>{ itemsPerPage=parseInt(document.getElementById('items-per-page').value); currentPage=1; renderPage(); };
  document.getElementById('prev').onclick=()=>{ changePage(-1); };
  document.getElementById('next').onclick=()=>{ changePage(1); };
  document.getElementById('search-box').addEventListener('input', ()=>{ applyFilters(); });

  // Initialize data
  fetchFileList();
  updateSelection();

</script>
</body>
</html>