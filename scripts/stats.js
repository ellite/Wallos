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
    if (this.hasAttribute('data-categoryid')) {
        const categoryId = this.getAttribute('data-categoryid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('category') === categoryId) {
            urlParams.delete('category');
        } else {
            urlParams.set('category', categoryId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    } else if (this.hasAttribute('data-memberid')) {
        const memberId = this.getAttribute('data-memberid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('member') === memberId) {
            urlParams.delete('member');
        } else {
            urlParams.set('member', memberId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    } else if (this.hasAttribute('data-paymentid')) {
        const paymentId = this.getAttribute('data-paymentid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('payment') === paymentId) {
            urlParams.delete('payment');
        } else {
            urlParams.set('payment', paymentId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    }
  });
});

function clearFilters() {
    window.location.href = 'stats.php';
}