const CHART_PALETTE = ['#008FFB','#00E396','#FEB019','#FF4560','#775DD0','#546E7A','#26a69a','#D10CE8'];

function _chartTheme() {
    const cs = getComputedStyle(document.documentElement);
    const main   = cs.getPropertyValue('--main-color').trim()   || '#007BFF';
    const text   = cs.getPropertyValue('--text-color').trim()   || '#202020';
    const border = cs.getPropertyValue('--box-border-color').trim() || '#E8E8E8';
    const dark   = text.toLowerCase() === '#e0e0e0';
    const font   = "Barlow, 'Helvetica Neue', Helvetica, sans-serif";
    return { main, text, border, dark, font };
}

function loadGraph(container, dataPoints, currency, run) {
    if (!run) return;

    const t = _chartTheme();
    const fmt = val => currency
        ? new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(val)
        : new Intl.NumberFormat(navigator.language).format(val);

    const hidden = new Array(dataPoints.length).fill(false);

    function activeSubset() {
        const pts = dataPoints.filter((_, i) => !hidden[i]);
        return {
            series: pts.map(p => p.y),
            labels:  pts.map(p => `${p.label} (${fmt(p.y)})`),
            colors: CHART_PALETTE.filter((_, i) => !hidden[i]).slice(0, pts.length),
        };
    }

    const el = document.getElementById(container);

    const chart = new ApexCharts(el, {
        chart: {
            type: 'donut',
            height: 320,
            background: 'transparent',
            fontFamily: t.font,
        },
        theme: { mode: t.dark ? 'dark' : 'light' },
        ...activeSubset(),
        legend: { show: false },
        dataLabels: {
            style: { fontFamily: t.font, fontSize: '12px' },
            dropShadow: { enabled: false },
        },
        plotOptions: { pie: { donut: { size: '55%' } } },
        stroke: { width: 0 },
        tooltip: {
            style: { fontFamily: t.font },
            y: { formatter: fmt },
        },
    });
    chart.render();

    const legend = document.createElement('div');
    legend.className = 'graph-legend';
    dataPoints.forEach((p, i) => {
        const item = document.createElement('button');
        item.className = 'graph-legend-item';
        item.innerHTML = `<span class="graph-legend-dot" style="background:${CHART_PALETTE[i % CHART_PALETTE.length]}"></span>${p.label} (${fmt(p.y)})`;
        item.addEventListener('click', () => {
            hidden[i] = !hidden[i];
            item.classList.toggle('graph-legend-item--off', hidden[i]);
            chart.updateOptions(activeSubset());
        });
        legend.appendChild(item);
    });
    el.after(legend);
}

function loadLineGraph(container, dataPoints, currency, run) {
    if (!run) return;

    const t = _chartTheme();
    const fmt = val => currency
        ? new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(val)
        : new Intl.NumberFormat(navigator.language).format(val);

    // Mark the 12-month high and low so the extremes read at a glance
    const values = dataPoints.map(p => p.y);
    const discreteMarkers = [];
    if (values.length > 2) {
        const maxIndex = values.indexOf(Math.max(...values));
        const minIndex = values.indexOf(Math.min(...values));
        if (maxIndex !== minIndex) {
            discreteMarkers.push(
                { seriesIndex: 0, dataPointIndex: maxIndex, size: 5, fillColor: t.main, strokeColor: t.dark ? '#171B23' : '#FFFFFF' },
                { seriesIndex: 0, dataPointIndex: minIndex, size: 5, fillColor: t.main, strokeColor: t.dark ? '#171B23' : '#FFFFFF' }
            );
        }
    }

    const chart = new ApexCharts(document.getElementById(container), {
        chart: {
            type: 'area',
            height: 370,
            background: 'transparent',
            fontFamily: t.font,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        theme: { mode: t.dark ? 'dark' : 'light' },
        series: [{ name: currency || '', data: dataPoints.map(p => p.y) }],
        xaxis: {
            categories: dataPoints.map(p => p.label),
            labels: { style: { fontFamily: t.font } },
        },
        yaxis: {
            labels: {
                formatter: fmt,
                style: { fontFamily: t.font },
            },
        },
        colors: [t.main],
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.0 },
        },
        markers: { size: 0, discrete: discreteMarkers, hover: { size: 5 } },
        grid: { borderColor: t.border },
        tooltip: {
            style: { fontFamily: t.font },
            y: { formatter: fmt },
        },
        legend: { show: false },
    });
    chart.render();
}

function loadBarGraph(container, dataPoints, currency, run, thresholdLine) {
    if (!run) return;

    const t = _chartTheme();
    const cs = getComputedStyle(document.documentElement);
    const errorColor = cs.getPropertyValue('--error-color').trim() || '#EF4444';
    const fmt = val => currency
        ? new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(val)
        : new Intl.NumberFormat(navigator.language).format(val);

    const annotations = {};
    if (thresholdLine !== null && thresholdLine !== undefined) {
        annotations.yaxis = [{
            y: thresholdLine,
            borderColor: errorColor,
            strokeDashArray: 5,
            label: {
                text: fmt(thresholdLine),
                position: 'left',
                textAnchor: 'start',
                style: {
                    color: errorColor,
                    background: 'transparent',
                    fontFamily: t.font,
                },
            },
        }];
    }

    const chart = new ApexCharts(document.getElementById(container), {
        chart: {
            type: 'bar',
            height: 320,
            background: 'transparent',
            fontFamily: t.font,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        theme: { mode: t.dark ? 'dark' : 'light' },
        series: [{ name: currency || '', data: dataPoints.map(p => p.y) }],
        xaxis: {
            categories: dataPoints.map(p => p.label),
            labels: { style: { fontFamily: t.font } },
        },
        yaxis: {
            labels: {
                formatter: fmt,
                style: { fontFamily: t.font },
            },
        },
        colors: [t.main],
        plotOptions: {
            bar: {
                columnWidth: '55%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: { enabled: false },
        annotations: annotations,
        grid: { borderColor: t.border },
        tooltip: {
            style: { fontFamily: t.font },
            y: { formatter: fmt },
        },
        legend: { show: false },
    });
    chart.render();
}

function loadHorizontalBarGraph(container, dataPoints, currency, run) {
    if (!run) return;

    const t = _chartTheme();
    const fmt = val => currency
        ? new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(val)
        : new Intl.NumberFormat(navigator.language).format(val);

    const chart = new ApexCharts(document.getElementById(container), {
        chart: {
            type: 'bar',
            height: Math.max(200, dataPoints.length * 36 + 60),
            background: 'transparent',
            fontFamily: t.font,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        theme: { mode: t.dark ? 'dark' : 'light' },
        series: [{ name: currency || '', data: dataPoints.map(p => p.y) }],
        xaxis: {
            categories: dataPoints.map(p => p.label),
            labels: {
                formatter: fmt,
                style: { fontFamily: t.font },
            },
        },
        yaxis: {
            labels: { style: { fontFamily: t.font } },
        },
        colors: [t.main],
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '55%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: t.border },
        tooltip: {
            style: { fontFamily: t.font },
            y: { formatter: fmt },
        },
        legend: { show: false },
    });
    chart.render();
}


function closeSubMenus() {
    var subMenus = document.querySelectorAll('.filtermenu-submenu-content');
    subMenus.forEach(subMenu => {
        subMenu.classList.remove('is-open');
    });

}

document.addEventListener("DOMContentLoaded", function() {
    var filtermenu = document.querySelector('#filtermenu-button');
    filtermenu.addEventListener('click', function() {
        this.parentElement.querySelector('.filtermenu-content').classList.toggle('is-open');
        closeSubMenus();
    });

    document.addEventListener('click', function(e) {
        var filtermenuContent = document.querySelector('.filtermenu-content');
        if (filtermenuContent.classList.contains('is-open')) {
            var subMenus = document.querySelectorAll('.filtermenu-submenu');
            var clickedInsideSubmenu = Array.from(subMenus).some(subMenu => subMenu.contains(e.target) || subMenu === e.target);

            if (!filtermenu.contains(e.target) && !clickedInsideSubmenu) {
                closeSubMenus();
                filtermenuContent.classList.remove('is-open');
            }
        }
    });
});

function toggleSubMenu(subMenu) {
    var subMenu = document.getElementById("filter-" + subMenu);
    if (subMenu.classList.contains("is-open")) {
        closeSubMenus();
    } else {
        closeSubMenus();
        subMenu.classList.add("is-open");
    }
}

document.querySelectorAll('.filter-item').forEach(function(item) {
  item.addEventListener('click', function(e) {
    const urlParams = new URLSearchParams(window.location.search);
    let newUrl = 'stats.php?';

    if (this.hasAttribute('data-categoryid')) {
        const categoryId = this.getAttribute('data-categoryid');
        const current = urlParams.get('category') ? urlParams.get('category').split(',') : [];
        const idx = current.indexOf(categoryId);
        if (idx !== -1) { current.splice(idx, 1); } else { current.push(categoryId); }
        current.length ? urlParams.set('category', current.join(',')) : urlParams.delete('category');
    } else if (this.hasAttribute('data-memberid')) {
        const memberId = this.getAttribute('data-memberid');
        const current = urlParams.get('member') ? urlParams.get('member').split(',') : [];
        const idx = current.indexOf(memberId);
        if (idx !== -1) { current.splice(idx, 1); } else { current.push(memberId); }
        current.length ? urlParams.set('member', current.join(',')) : urlParams.delete('member');
    } else if (this.hasAttribute('data-paymentid')) {
        const paymentId = this.getAttribute('data-paymentid');
        const current = urlParams.get('payment') ? urlParams.get('payment').split(',') : [];
        const idx = current.indexOf(paymentId);
        if (idx !== -1) { current.splice(idx, 1); } else { current.push(paymentId); }
        current.length ? urlParams.set('payment', current.join(',')) : urlParams.delete('payment');
    }

    newUrl += urlParams.toString();
    window.location.href = newUrl;
  });
});

function clearFilters() {
    window.location.href = 'stats.php';
}