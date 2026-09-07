{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        <!-- AdminLTE Standard Box -->
        <div class="box box-success">
            
            <div class="box-header with-border">
                <div class="row">
                    <div class="col-md-6 col-sm-12">
                        <h3 class="box-title" style="margin-top: 5px;">
                            <i class="fa fa-plug text-green"></i> PPPoE Online Users 
                            <span class="label label-success">{$online_users|@count} Online</span>
                        </h3>
                        <button id="refreshBtn" class="btn btn-default btn-xs" onclick="location.reload();" title="Refresh" style="margin-left: 5px;">
                            <i class="fa fa-refresh"></i>
                        </button>
                    </div>
                    
                    <div class="col-md-6 col-sm-12 text-right">
                        <div class="form-inline">
                            <div class="form-group margin-r-5">
                                <label class="control-label">Show </label>
                                <select id="entriesLengthSelector" class="form-control input-sm">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="250">250</option>
                                    <option value="500">500</option>
                                    <option value="all">All</option>
                                </select>
                                <label class="control-label"> entries</label>
                            </div>
                            
                            <div class="form-group">
                                <div class="input-group input-group-sm" style="width: 220px;">
                                    <input type="text" id="searchBox" class="form-control pull-right" placeholder="Search Name, IP, MAC...">
                                    <div class="input-group-btn">
                                        <button class="btn btn-default" onclick="triggerSearch()"><i class="fa fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Box Body -->
            <div class="box-body table-responsive no-padding">

                {if $online_users|@count > 0}

                <table class="table table-hover table-striped" id="userTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Router</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Address</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Download</th>
                            <th>Upload</th>
                            <th>Total</th>
                            <th>Uptime</th>
                            <th class="text-center">Live Traffic</th>
                            <th class="text-center" style="width: 100px;">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        {assign var="i" value=1}
                        {foreach $online_users as $user}
                            {if $user.service_type == 'PPPoE' || $user.service_type == 'pppoe'}
                            <tr class="user-data-row" style="display: none;">
                                <td>{$i}</td>
                                <td><span class="label label-default">{$user.router_name}</span></td>
                                <td>
                                    <a href="index.php?_route=customers/viewu/{$user.name|urlencode}" class="text-bold">
                                        {$user.name}
                                    </a>
                                </td>
                                <td>{$user.fullname|default:'-'}</td>
                                <td><span class="text-muted">{$user.address|default:'-'}</span></td>
                                <td><code>{$user.ip}</code></td>
                                <td><small class="text-muted">{$user.mac}</small></td>
                                <td><span class="label label-info">{$user.service_type|default:'PPPoE'}</span></td>

                                <td>
                                    {assign var="stat" value=$user.status|default:'on'}
                                    {if $stat == 'off'}
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

                                <td class="text-center">
                                    <button class="btn btn-xs btn-success" onclick="openTrafficModal('{$user.router_id}', '{$user.name}', '{$user.service_type}', '{$user.router_name}')">
                                        <i class="fa fa-area-chart"></i> Traffic
                                    </button>
                                </td>

                                <td class="text-center">
                                    <button class="btn btn-xs btn-danger" onclick="disconnectUser('{$user.router_id}', '{$user.name}', '{$user.service_type}')">
                                        <i class="fa fa-power-off"></i> Kick
                                    </button>
                                </td>
                            </tr>
                            {assign var="i" value=$i+1}
                            {/if}
                        {/foreach}
                    </tbody>
                </table>

                <!-- AdminLTE Footer Pagination -->
                <div class="box-footer clearfix" id="tablePaginationFooter">
                    <div class="row">
                        <div class="col-sm-5">
                            <div class="dataTables_info" id="infoText" style="padding-top: 6px;">
                                Showing <span id="displayedCount" class="text-bold">0</span> to <span id="endCount" class="text-bold">0</span> of <span id="totalCount" class="text-bold">0</span> entries
                            </div>
                        </div>
                        <div class="col-sm-7">
                            <div id="paginationButtonsArea" class="pull-right"></div>
                        </div>
                    </div>
                </div>

                {else}
                    <div class="pad margin no-print">
                        <div class="callout callout-warning" style="margin-bottom: 0!important;">
                            <h4><i class="fa fa-info-circle"></i> Info:</h4>
                            No PPPoE users online at this moment.
                        </div>
                    </div>
                {/if}

            </div>
        </div>
    </div>
</div>

<!-- AdminLTE Live Traffic Modal -->
<div class="modal fade" id="trafficModal" tabindex="-1" role="dialog" aria-labelledby="trafficModalLabel" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header bg-green">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeTrafficModal()">
            <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="trafficModalLabel">
            <i class="fa fa-line-chart"></i> Live Bandwidth Monitor
        </h4>
      </div>

      <div class="modal-body">
        <div class="row text-center margin-bottom">
          <div class="col-xs-4">
              <div class="well well-sm no-margin">
                  <span class="text-muted text-uppercase" style="font-size: 10px; font-weight:700;">Target User</span>
                  <div id="modalUsername" class="text-bold text-ellipsis">-</div>
              </div>
          </div>
          <div class="col-xs-4">
              <div class="well well-sm no-margin">
                  <span class="text-muted text-uppercase" style="font-size: 10px; font-weight:700;">Service</span>
                  <div id="modalService" class="text-bold text-blue">-</div>
              </div>
          </div>
          <div class="col-xs-4">
              <div class="well well-sm no-margin">
                  <span class="text-muted text-uppercase" style="font-size: 10px; font-weight:700;">Router</span>
                  <div id="modalRouter" class="text-bold text-orange text-ellipsis">-</div>
              </div>
          </div>
        </div>

        <div class="row text-center margin-bottom">
          <div class="col-xs-6">
            <div class="small-box bg-green" style="margin-bottom:0;">
              <div class="inner" style="padding:10px;">
                <p style="margin:0; font-size:11px; text-transform:uppercase;">DOWNLOAD</p>
                <h3 id="modalDownloadWidget" style="font-size:22px; margin:0;">0 bps</h3>
              </div>
            </div>
          </div>
          <div class="col-xs-6">
            <div class="small-box bg-red" style="margin-bottom:0;">
              <div class="inner" style="padding:10px;">
                <p style="margin:0; font-size:11px; text-transform:uppercase;">UPLOAD</p>
                <h3 id="modalUploadWidget" style="font-size:22px; margin:0;">0 bps</h3>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-sm-12">
            <div style="position: relative; height: 200px;">
                <canvas id="trafficGraph"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer text-center">
        <button type="button" class="btn btn-default pull-right" data-dismiss="modal" onclick="closeTrafficModal()">
            <i class="fa fa-times"></i> Close
        </button>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

{literal}
<script>
let entriesLimit = 10;
let isSearchMode = false;
let totalRows = 0;
let cachedRows = [];

function initTablePagination() {
    cachedRows = Array.from(document.querySelectorAll("#userTable tbody tr.user-data-row"));
    totalRows = cachedRows.length;
    
    var selector = document.getElementById("entriesLengthSelector");
    if(selector) {
        selector.addEventListener("change", function() {
            let val = this.value;
            entriesLimit = (val === "all") ? totalRows : parseInt(val, 10);
            renderTableCoreView();
        });
    }

    renderTableCoreView();
}

function renderTableCoreView() {
    var footer = document.getElementById("tablePaginationFooter");
    if (isSearchMode) {
        if(footer) footer.style.display = "none";
        return;
    }

    if(footer) footer.style.display = "block";
    document.getElementById("totalCount").innerText = totalRows;

    let visibleCounter = 0;
    for (let i = 0; i < totalRows; i++) {
        if (i < entriesLimit) {
            cachedRows[i].style.display = "";
            visibleCounter++;
        } else {
            cachedRows[i].style.display = "none";
        }
    }

    document.getElementById("displayedCount").innerText = (totalRows > 0) ? 1 : 0;
    document.getElementById("endCount").innerText = visibleCounter;
}

function triggerSearch(){
    let value = document.getElementById('searchBox').value.toLowerCase().trim();
    var footer = document.getElementById("tablePaginationFooter");

    if (value === "") {
        isSearchMode = false;
        renderTableCoreView();
        return;
    }

    isSearchMode = true;
    if(footer) footer.style.display = "none";

    for (let i = 0; i < totalRows; i++) {
        let rowText = cachedRows[i].textContent.toLowerCase();
        cachedRows[i].style.display = rowText.includes(value) ? "" : "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    initTablePagination();

    var searchBox = document.getElementById('searchBox');
    if(searchBox) {
        searchBox.addEventListener('keyup', function(e){
            triggerSearch();
        });
    }

    var refreshBtn = document.getElementById('refreshBtn');
    if(refreshBtn) {
        refreshBtn.addEventListener('click', function(){ location.reload(); });
    }
});

let trafficChart = null;
let trafficInterval = null;
let prevTraffic = {};

function openTrafficModal(routerId, username, service, routerName){
    document.getElementById('modalUsername').innerText = username;
    document.getElementById('modalService').innerText = service;
    document.getElementById('modalRouter').innerText = routerName;
    
    $('#trafficModal').modal('show');

    let ctx = document.getElementById('trafficGraph').getContext('2d');
    
    const dlGradient = ctx.createLinearGradient(0, 0, 0, 150);
    dlGradient.addColorStop(0, 'rgba(0, 166, 90, 0.3)');
    dlGradient.addColorStop(1, 'rgba(0, 166, 90, 0.0)');

    const ulGradient = ctx.createLinearGradient(0, 0, 0, 150);
    ulGradient.addColorStop(0, 'rgba(221, 75, 57, 0.3)');
    ulGradient.addColorStop(1, 'rgba(221, 75, 57, 0.0)');

    let initialLabels = new Array(15).fill('');
    let initialDl = new Array(15).fill(0);
    let initialUl = new Array(15).fill(0);

    if (trafficChart) { trafficChart.destroy(); }

    trafficChart = new Chart(ctx, {
        type: 'line',
        data: { 
            labels: initialLabels, 
            datasets: [
                { 
                    label: 'Download', data: initialDl, borderColor: '#00a65a', borderWidth: 2,
                    backgroundColor: dlGradient, fill: true, tension: 0.4, pointRadius: 0
                },
                { 
                    label: 'Upload', data: initialUl, borderColor: '#dd4b39', borderWidth: 2,
                    backgroundColor: ulGradient, fill: true, tension: 0.4, pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 300 },
            interaction: { mode: 'index', intersect: false },
            plugins: { 
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10 } } } 
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 8 } } },
                y: { beginAtZero: true, suggestedMin: 0, grid: { borderDash: [4, 4] }, ticks: { font: { size: 8 }, callback: function(v){ return formatBits(v); } } }
            }
        }
    });

    updateModalTraffic(routerId, username, service);
    if(trafficInterval) clearInterval(trafficInterval);
    trafficInterval = setInterval(()=>updateModalTraffic(routerId, username, service), 2000);
}

function closeTrafficModal(){
    if(trafficInterval) { clearInterval(trafficInterval); trafficInterval = null; }
    if(trafficChart) { trafficChart.destroy(); trafficChart = null; }
    $('#trafficModal').modal('hide');
}

function updateModalTraffic(routerId, username, service){
    let targetRoute = (service === 'PPPoE' || service === 'pppoe') ? 'plugin/pppoe_online_ui' : 'plugin/hotspot_online_ui';

    fetch('index.php?_route=' + targetRoute + '&ajax=1')
    .then(res=>res.json())
    .then(users=>{
        let user = users.find(u=>u.router_id==routerId && u.name==username);
        if(!user) return;

        let now = Date.now();
        let cacheKey = routerId + '_' + username;

        let currentDownloadBytes = parseFloat(user.upload || 0); 
        let currentUploadBytes = parseFloat(user.download || 0);

        if(!prevTraffic[cacheKey]){
            prevTraffic[cacheKey] = { upload: currentUploadBytes, download: currentDownloadBytes, time: now };
            return;
        }

        let prev = prevTraffic[cacheKey];
        let timeDiff = (now - prev.time) / 1000;
        if(timeDiff <= 0) timeDiff = 2;

        let liveDownload = Math.max(0, (currentDownloadBytes - prev.download) / timeDiff);
        let liveUpload = Math.max(0, (currentUploadBytes - prev.upload) / timeDiff);

        prevTraffic[cacheKey] = { upload: currentUploadBytes, download: currentDownloadBytes, time: now };

        document.getElementById('modalDownloadWidget').innerText = formatBits(liveDownload);
        document.getElementById('modalUploadWidget').innerText = formatBits(liveUpload);

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
    }).catch(err => console.error("Traffic fetch error:", err));
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
        $('#version').html('PPPoE Online Users Plugin | Ver: 3.1 | by: <a href="' + portalLink + '" target="_blank">BipulDhali</a>');
    });
</script>

{include file="sections/footer.tpl"}
