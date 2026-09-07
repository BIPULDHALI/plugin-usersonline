{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">

            <!-- Box Header -->
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-wifi text-primary"></i> Hotspot Online Users 
                    <span class="label label-success" style="margin-left: 5px;">{$online_users|@count} Online</span>
                </h3>
                <div class="box-tools pull-right">
                    <button type="button" id="refreshBtn" class="btn btn-box-tool" data-toggle="tooltip" title="Refresh">
                        <i class="fa fa-refresh"></i>
                    </button>
                </div>
            </div>

            <!-- Box Body Controls & Table -->
            <div class="box-body">

                <!-- Controls Area (Length Selector & Search) -->
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-sm-6 col-xs-12">
                        <div class="form-inline">
                            <label style="font-weight: normal;">
                                Show 
                                <select id="entriesLengthSelector" class="form-control input-sm" style="width: auto; display: inline-block; margin: 0 5px;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="250">250</option>
                                    <option value="500">500</option>
                                    <option value="all">All</option>
                                </select>
                                entries
                            </label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xs-12">
                        <div class="input-group input-group-sm pull-right" style="max-width: 300px; width: 100%;">
                            <input type="text" id="searchBox" class="form-control" placeholder="Search Name, IP, MAC...">
                            <span class="input-group-btn">
                                <button class="btn btn-primary btn-flat" type="button" onclick="triggerSearch()">
                                    <i class="fa fa-search"></i> Search
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                {if $online_users|@count > 0}
                <!-- Table Container -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="userTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Router</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Address</th>
                                <th>IP</th>
                                <th>MAC</th>
                                <th>Service Type</th>
                                <th>Status</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Total</th>
                                <th>Uptime</th>
                                <th>Live Traffic</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            {assign var="i" value=1}
                            {foreach $online_users as $user}
                                {if $user.service_type == 'Hotspot'}
                                <tr class="user-data-row" style="display: none;">
                                    <td>{$i}</td>
                                    <td><span class="label label-default">{$user.router_name}</span></td>
                                    <td>
                                        <a href="?_route=customers/viewu/{$user.name|urlencode}">
                                            <strong>{$user.name}</strong>
                                        </a>
                                    </td>
                                    <td>{$user.fullname|default:'-'}</td>
                                    <td><span class="text-muted">{$user.address|default:'-'}</span></td>
                                    <td><code>{$user.ip}</code></td>
                                    <td><small class="text-muted">{$user.mac}</small></td>
                                    <td><span class="label label-info">{$user.service_type|default:'Hotspot'}</span></td>

                                    <td>
                                        {assign var="stat" value=$user.status|default:'on'}
                                        {if $stat == 'on'}
                                            <span class="label label-success">Online</span>
                                        {elseif $stat == 'off'}
                                            <span class="label label-danger">Offline</span>
                                        {elseif $stat == 'expired'}
                                            <span class="label label-warning">Expired</span>
                                        {else}
                                            <span class="label label-success">Online</span>
                                        {/if}
                                    </td>

                                    <td><span class="text-green"><i class="fa fa-arrow-down"></i> {formatBytes($user.upload)}</span></td>
                                    <td><span class="text-red"><i class="fa fa-arrow-up"></i> {formatBytes($user.download)}</span></td>
                                    <td><strong>{formatBytes($user.total)}</strong></td>
                                    <td><small class="text-muted">{$user.uptime}</small></td>

                                    <td>
                                        <button class="btn btn-xs btn-success" onclick="openTrafficModal('{$user.router_id}', '{$user.name}', '{$user.service_type}', '{$user.router_name}')">
                                            <i class="fa fa-line-chart"></i> Traffic
                                        </button>
                                    </td>

                                    <td>
                                        <button class="btn btn-xs btn-danger" onclick="disconnectUser('{$user.router_id}', '{$user.name}', '{$user.service_type}')">
                                            <i class="fa fa-power-off"></i> Disconnect
                                        </button>
                                    </td>
                                </tr>
                                {assign var="i" value=$i+1}
                                {/if}
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <!-- Footer Pagination Controls -->
                <div class="row" id="tablePaginationFooter" style="margin-top: 10px;">
                    <div class="col-sm-5">
                        <div class="dataTables_info" id="infoText">
                            Showing <span id="displayedCount" style="font-weight: bold;">0</span> to <span id="endCount" style="font-weight: bold;">0</span> of <span id="totalCount" style="font-weight: bold;">0</span> entries
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div id="paginationButtonsArea" class="pull-right"></div>
                    </div>
                </div>

                {else}
                    <div class="alert alert-warning alert-dismissible" style="margin-bottom: 0;">
                        <i class="icon fa fa-warning"></i> No Hotspot users online.
                    </div>
                {/if}

            </div>
        </div>
    </div>
</div>

<!-- Live Traffic Modal (AdminLTE / Bootstrap Standard) -->
<div class="modal fade" id="trafficModal" tabindex="-1" role="dialog" aria-labelledby="trafficModalLabel" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header bg-primary">
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="closeTrafficModal()"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="trafficModalLabel">
            <i class="fa fa-line-chart"></i> Live Bandwidth Monitor
        </h4>
      </div>

      <div class="modal-body">

        <div class="row text-center" style="margin-bottom: 15px;">
          <div class="col-xs-4">
              <div class="well well-sm" style="margin-bottom: 0;">
                  <small class="text-muted text-uppercase display-block">Target User</small>
                  <h4 id="modalUsername" style="margin: 5px 0 0 0;">-</h4>
              </div>
          </div>
          <div class="col-xs-4">
              <div class="well well-sm" style="margin-bottom: 0;">
                  <small class="text-muted text-uppercase display-block">Service</small>
                  <h4 id="modalService" class="text-info" style="margin: 5px 0 0 0;">-</h4>
              </div>
          </div>
          <div class="col-xs-4">
              <div class="well well-sm" style="margin-bottom: 0;">
                  <small class="text-muted text-uppercase display-block">Router Name</small>
                  <h4 id="modalRouter" class="text-warning" style="margin: 5px 0 0 0;">-</h4>
              </div>
          </div>
        </div>

        <div class="row text-center" style="margin-bottom: 15px;">
          <div class="col-xs-6">
            <div class="info-box bg-green">
              <span class="info-box-icon"><i class="fa fa-arrow-down"></i></span>
              <div class="info-box-content" style="padding-top: 10px;">
                <span class="info-box-text">DOWNLOAD</span>
                <span id="modalDownloadWidget" class="info-box-number" style="font-size: 20px;">0 bps</span>
              </div>
            </div>
          </div>
          <div class="col-xs-6">
            <div class="info-box bg-red">
              <span class="info-box-icon"><i class="fa fa-arrow-up"></i></span>
              <div class="info-box-content" style="padding-top: 10px;">
                <span class="info-box-text">UPLOAD</span>
                <span id="modalUploadWidget" class="info-box-number" style="font-size: 20px;">0 bps</span>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-sm-12">
            <div class="chart-responsive" style="height: 250px;">
                <canvas id="trafficGraph"></canvas>
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default pull-right" data-dismiss="modal" onclick="closeTrafficModal()">Close</button>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

{literal}
<script>
let entriesLimit = 10;
let isSearchMode = false;
let cachedRows = [];
let totalRows = 0;

let elTableFooter, elTotalCount, elDisplayedCount, elEndCount, elSearchBox;

function initTablePagination() {
    cachedRows = Array.from(document.querySelectorAll("#userTable tbody tr.user-data-row"));
    totalRows = cachedRows.length;
    
    elTableFooter = document.getElementById("tablePaginationFooter");
    elTotalCount = document.getElementById("totalCount");
    elDisplayedCount = document.getElementById("displayedCount");
    elEndCount = document.getElementById("endCount");
    elSearchBox = document.getElementById("searchBox");

    document.getElementById("entriesLengthSelector").addEventListener("change", function() {
        let val = this.value;
        entriesLimit = (val === "all") ? totalRows : parseInt(val, 10);
        renderTableCoreView();
    });

    renderTableCoreView();
}

function renderTableCoreView() {
    if (isSearchMode) {
        if(elTableFooter) elTableFooter.style.display = "none";
        return;
    }

    if(elTableFooter) elTableFooter.style.display = "";
    if(elTotalCount) elTotalCount.textContent = totalRows;

    let visibleCounter = 0;
    for (let i = 0; i < totalRows; i++) {
        if (i < entriesLimit) {
            cachedRows[i].style.display = "";
            visibleCounter++;
        } else {
            cachedRows[i].style.display = "none";
        }
    }

    if(elDisplayedCount) elDisplayedCount.textContent = (totalRows > 0) ? 1 : 0;
    if(elEndCount) elEndCount.textContent = visibleCounter;
}

function triggerSearch(){
    let value = elSearchBox.value.toLowerCase().trim();

    if (value === "") {
        isSearchMode = false;
        renderTableCoreView();
        return;
    }

    isSearchMode = true;
    if(elTableFooter) elTableFooter.style.display = "none";

    for (let i = 0; i < totalRows; i++) {
        let row = cachedRows[i];
        let textContent = row.textContent.toLowerCase();
        row.style.display = textContent.includes(value) ? "" : "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    initTablePagination();

    if (elSearchBox) {
        elSearchBox.addEventListener('keyup', function(e){
            if (e.key === 'Enter' || this.value === "") {
                triggerSearch();
            }
        });
    }

    let refreshBtn = document.getElementById('refreshBtn');
    if(refreshBtn) {
        refreshBtn.addEventListener('click', function(){ location.reload(); });
    }
});

function isDarkModeActive() {
    return document.body.classList.contains('dark-mode') || 
           document.body.classList.contains('dark') || 
           document.documentElement.getAttribute('data-theme') === 'dark';
}

let trafficChart = null;
let trafficInterval = null;
let prevTraffic = {};

function openTrafficModal(routerId, username, service, routerName){
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('modalService').textContent = service;
    document.getElementById('modalRouter').textContent = routerName;
    
    $('#trafficModal').modal('show');

    const isDark = isDarkModeActive();
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
    const labelColor = isDark ? '#94a3b8' : '#64748b';

    let canvas = document.getElementById('trafficGraph');
    let ctx = canvas.getContext('2d');
    
    const dlGradient = ctx.createLinearGradient(0, 0, 0, 150);
    dlGradient.addColorStop(0, 'rgba(0, 166, 90, 0.3)');
    dlGradient.addColorStop(1, 'rgba(0, 166, 90, 0.0)');

    const ulGradient = ctx.createLinearGradient(0, 0, 0, 150);
    ulGradient.addColorStop(0, 'rgba(221, 75, 57, 0.3)');
    ulGradient.addColorStop(1, 'rgba(221, 75, 57, 0.0)');

    let initialLabels = new Array(15).fill('');
    let initialDl = new Array(15).fill(0);
    let initialUl = new Array(15).fill(0);

    if (trafficChart) {
        trafficChart.destroy();
    }

    trafficChart = new Chart(ctx, {
        type: 'line',
        data: { 
            labels: initialLabels, 
            datasets: [
                { 
                    label: 'Download', data: initialDl, borderColor: '#00a65a', borderWidth: 2,
                    backgroundColor: dlGradient, fill: true, tension: 0.3, pointRadius: 0
                },
                { 
                    label: 'Upload', data: initialUl, borderColor: '#dd4b39', borderWidth: 2,
                    backgroundColor: ulGradient, fill: true, tension: 0.3, pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true, 
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { 
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, color: labelColor, font: { size: 10 } } } 
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: labelColor, font: { size: 8 } } },
                y: { beginAtZero: true, suggestedMin: 0, grid: { color: gridColor, borderDash: [4, 4] }, ticks: { color: labelColor, font: { size: 8 }, callback: function(v){ return formatBits(v); } } }
            }
        }
    });

    updateModalTraffic(routerId, username, service);
    if(trafficInterval) clearInterval(trafficInterval);
    trafficInterval = setInterval(()=>updateModalTraffic(routerId, username, service), 2000);
}

function closeTrafficModal(){
    if(trafficInterval) clearInterval(trafficInterval);
    if(trafficChart) { trafficChart.destroy(); trafficChart = null; }
    $('#trafficModal').modal('hide');
}

function updateModalTraffic(routerId, username, service){
    fetch('index.php?_route=plugin/hotspot_online_ui&ajax=1')
    .then(res => res.json())
    .then(users => {
        let user = users.find(u => u.router_id == routerId && u.name == username);
        if(!user) return;

        let now = Date.now();
        let cacheKey = routerId + '_' + username;

        if(!prevTraffic[cacheKey]){
            prevTraffic[cacheKey] = { upload: parseFloat(user.upload || 0), download: parseFloat(user.download || 0), time: now };
            return;
        }

        let prev = prevTraffic[cacheKey];
        let timeDiff = (now - prev.time) / 1000;
        if(timeDiff <= 0) timeDiff = 2;

        let liveDownload = (parseFloat(user.upload || 0) - prev.upload) / timeDiff;
        let liveUpload = (parseFloat(user.download || 0) - prev.download) / timeDiff;

        if(liveDownload < 0) liveDownload = 0;
        if(liveUpload < 0) liveUpload = 0;

        prevTraffic[cacheKey] = { upload: parseFloat(user.upload || 0), download: parseFloat(user.download || 0), time: now };

        document.getElementById('modalDownloadWidget').textContent = formatBits(liveDownload);
        document.getElementById('modalUploadWidget').textContent = formatBits(liveUpload);

        if(!trafficChart) return;

        trafficChart.data.labels.shift();
        trafficChart.data.datasets[0].data.shift();
        trafficChart.data.datasets[1].data.shift();

        let timeLabel = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        trafficChart.data.labels.push(timeLabel);
        trafficChart.data.datasets[0].data.push(liveDownload); 
        trafficChart.data.datasets[1].data.push(liveUpload);   
        trafficChart.data.datasets[0].label = 'Download (' + formatBits(liveDownload) + ')';
        trafficChart.data.datasets[1].label = 'Upload (' + formatBits(liveUpload) + ')';
        trafficChart.update('none');
    })
    .catch(err => console.error(err));
}

function formatBits(bytesPerSec){
    if(bytesPerSec <= 0 || isNaN(bytesPerSec)) return '0 bps';
    let bits = bytesPerSec * 8; 
    let units = ['bps','Kbps','Mbps','Gbps','Tbps'];
    let i = Math.floor(Math.log(bits)/Math.log(1000));
    if (i < 0) return bits + ' bps';
    i = Math.min(i, units.length - 1);
    return (bits / Math.pow(1000, i)).toFixed(2) + ' ' + units[i];
}

function disconnectUser(routerId, username, service){
    if(!confirm('Are you sure you want to disconnect ' + username + '?')) return;
    fetch('index.php?_route=plugin/disconnect_user&router_id=' + routerId + '&user_id=' + encodeURIComponent(username) + '&service_type=' + service)
    .then(res => res.json())
    .then(data => {
        if(data.status){ alert(username + ' disconnected successfully!'); location.reload(); }
        else { alert('Error: ' + (data.msg || 'Could not disconnect user')); }
    });
}
</script>
{/literal}

<script>
    window.addEventListener('DOMContentLoaded', function () {
        var portalLink = "https://github.com/bipuldhali";
        $('#version').html('Hotspot Online Users Plugin | Ver: 3.1 | by: <a href="' + portalLink + '" target="_blank">BipulDhali</a>');
    });
</script>

{include file="sections/footer.tpl"}
