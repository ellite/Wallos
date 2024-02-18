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
            },
        });
    }
}
