function loadGraph(container, dataPoints, currency, run) {
    if (run) {
        var ctx = document.getElementById(container).getContext('2d');

        var chart = new Chart(ctx, {
            type: 'pie',
            data: {
                datasets: [{
                    data: dataPoints.map(point => point.y),
                }],
                labels: dataPoints.map(point => {
                    if (currency) {
                        return `${point.label} (${new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(point.y)})`;
                    } else {
                        return `${point.label} (${new Intl.NumberFormat(navigator.language).format(point.y)})`;
                    }
                }),
            },
            options: {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = " ";
                                if (currency) {
                                    label += new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(context.raw);
                                } else {
                                    label += new Intl.NumberFormat(navigator.language).format(context.raw);
                                }
                                return label;
                            }
                        }
                    }
                }
            },
        });
    }
}

function loadLineGraph(container, dataPoints, currency, run) {
    if (run) {
        var ctx = document.getElementById(container).getContext('2d');

        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: '',
                    data: dataPoints.map(point => point.y),
                }],
                labels: dataPoints.map(point => {
                    return `${point.label}`;
                }),
            },
            options: {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value, index, values) {
                                if (currency) {
                                    return new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(value);
                                } else {
                                    return new Intl.NumberFormat(navigator.language).format(value);
                                }
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
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