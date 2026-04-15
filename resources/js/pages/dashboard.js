import * as echarts from "echarts";

const labelColor = "#94a3b8";
const lineColor = "#e2e8f0";

const donutBase = {
    tooltip: { trigger: "item", formatter: "{b}: {c} ({d}%)" },
    legend: {
        bottom: "2%",
        left: "center",
        textStyle: { color: labelColor, fontSize: 11 },
    },
    backgroundColor: "transparent",
};

window._chartOpts = {
    belumSudah: {
        ...donutBase,
        series: [
            {
                type: "pie",
                radius: ["45%", "68%"],
                center: ["50%", "45%"],
                label: {
                    show: true,
                    formatter: "{b}\n{d}%",
                    fontSize: 11,
                    color: labelColor,
                },
                labelLine: { show: true, length: 10, length2: 8 },
                data: [
                    {
                        value: 350,
                        name: "Belum Diperiksa",
                        itemStyle: { color: "#f97316" },
                    },
                    {
                        value: 1950,
                        name: "Sudah Diperiksa",
                        itemStyle: { color: "#22c55e" },
                    },
                    {
                        value: 78,
                        name: "Dalam Pelayanan",
                        itemStyle: { color: "#3b82f6" },
                    },
                ],
            },
        ],
    },

    registrasiHarian: {
        backgroundColor: "transparent",
        tooltip: { trigger: "axis", axisPointer: { type: "cross" } },
        grid: { left: 40, right: 20, top: 10, bottom: 40 },
        xAxis: {
            type: "category",
            boundaryGap: false,
            data: Array.from({ length: 31 }, (_, i) => `${i + 1} Mar`),
            axisLabel: { color: labelColor, fontSize: 10, interval: 4 },
            axisLine: { lineStyle: { color: lineColor } },
            splitLine: { show: false },
        },
        yAxis: {
            type: "value",
            axisLabel: { color: labelColor, fontSize: 10 },
            splitLine: { lineStyle: { color: lineColor, type: "dashed" } },
        },
        series: [
            {
                name: "Registrasi",
                type: "line",
                smooth: true,
                symbol: "circle",
                symbolSize: 5,
                lineStyle: { color: "#6366f1", width: 2.5 },
                itemStyle: { color: "#6366f1" },
                areaStyle: {
                    color: {
                        type: "linear",
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: "rgba(99,102,241,0.35)" },
                            { offset: 1, color: "rgba(99,102,241,0.02)" },
                        ],
                    },
                },
                data: [
                    12, 8, 15, 20, 10, 5, 25, 18, 7, 12, 22, 19, 8, 14, 11, 23,
                    17, 9, 21, 16, 13, 6, 24, 18, 10, 15, 20, 14, 8, 18, 22,
                ],
            },
        ],
    },

    distribusiDesa: {
        backgroundColor: "transparent",
        tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
        grid: { left: 90, right: 60, top: 10, bottom: 30 },
        xAxis: {
            type: "value",
            axisLabel: { color: labelColor, fontSize: 10 },
            splitLine: { lineStyle: { color: lineColor, type: "dashed" } },
        },
        yAxis: {
            type: "category",
            data: [
                "Ngreco",
                "Kasihan",
                "Gemaharjo",
                "Tegalombo",
                "Pulosari",
                "Krisikan",
                "Kanigoro",
                "Baruharjo",
            ],
            axisLabel: { color: labelColor, fontSize: 11 },
            axisLine: { lineStyle: { color: lineColor } },
        },
        series: [
            {
                name: "Pasien",
                type: "bar",
                barMaxWidth: 28,
                itemStyle: {
                    color: {
                        type: "linear",
                        x: 0,
                        y: 0,
                        x2: 1,
                        y2: 0,
                        colorStops: [
                            { offset: 0, color: "#60a5fa" },
                            { offset: 1, color: "#6366f1" },
                        ],
                    },
                    borderRadius: [0, 6, 6, 0],
                },
                label: {
                    show: true,
                    position: "right",
                    color: labelColor,
                    fontSize: 10,
                },
                data: [112, 145, 170, 198, 255, 280, 315, 420],
            },
        ],
    },

    jenisKelamin: {
        ...donutBase,
        series: [
            {
                type: "pie",
                radius: ["45%", "68%"],
                center: ["50%", "45%"],
                label: {
                    show: true,
                    formatter: "{b}\n{d}%",
                    fontSize: 11,
                    color: labelColor,
                },
                labelLine: { show: true, length: 10, length2: 8 },
                data: [
                    {
                        value: 1085,
                        name: "Laki-laki",
                        itemStyle: { color: "#3b82f6" },
                    },
                    {
                        value: 1293,
                        name: "Perempuan",
                        itemStyle: { color: "#ec4899" },
                    },
                ],
            },
        ],
    },

    jenisCkg: {
        ...donutBase,
        series: [
            {
                type: "pie",
                radius: ["45%", "68%"],
                center: ["50%", "45%"],
                label: {
                    show: true,
                    formatter: "{b}\n{d}%",
                    fontSize: 11,
                    color: labelColor,
                },
                labelLine: { show: true, length: 10, length2: 8 },
                data: [
                    {
                        value: 860,
                        name: "CKG Sekolah",
                        itemStyle: { color: "#a855f7" },
                    },
                    {
                        value: 1518,
                        name: "CKG Umum",
                        itemStyle: { color: "#f59e0b" },
                    },
                ],
            },
        ],
    },

    pegawaiRank: {
        backgroundColor: "transparent",
        tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
        grid: { left: 120, right: 30, top: 10, bottom: 20 },
        xAxis: {
            type: "value",
            axisLabel: { color: labelColor, fontSize: 10 },
            splitLine: { lineStyle: { color: lineColor, type: "dashed" } },
        },
        yAxis: {
            type: "category",
            inverse: true,
            data: [],
            axisLabel: { color: labelColor, fontSize: 11 },
            axisLine: { lineStyle: { color: lineColor } },
        },
        series: [
            {
                name: "Jumlah CKG",
                type: "bar",
                barMaxWidth: 26,
                itemStyle: {
                    color: {
                        type: "linear",
                        x: 0,
                        y: 0,
                        x2: 1,
                        y2: 0,
                        colorStops: [
                            { offset: 0, color: "#14b8a6" },
                            { offset: 1, color: "#0f766e" },
                        ],
                    },
                    borderRadius: [0, 6, 6, 0],
                },
                label: {
                    show: true,
                    position: "right",
                    color: labelColor,
                    fontSize: 10,
                },
                data: [],
            },
        ],
    },

    capaianBulananIndividu: {
        backgroundColor: "transparent",
        tooltip: { trigger: "axis", axisPointer: { type: "line" } },
        grid: { left: 40, right: 20, top: 10, bottom: 35 },
        xAxis: {
            type: "category",
            data: [
                "Jan",
                "Feb",
                "Mar",
                "Apr",
                "Mei",
                "Jun",
                "Jul",
                "Agu",
                "Sep",
                "Okt",
                "Nov",
                "Des",
            ],
            axisLabel: { color: labelColor, fontSize: 10 },
            axisLine: { lineStyle: { color: lineColor } },
            splitLine: { show: false },
        },
        yAxis: {
            type: "value",
            axisLabel: { color: labelColor, fontSize: 10 },
            splitLine: { lineStyle: { color: lineColor, type: "dashed" } },
        },
        series: [
            {
                name: "Selesai CKG",
                type: "bar",
                barMaxWidth: 26,
                itemStyle: {
                    color: {
                        type: "linear",
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: "#6366f1" },
                            { offset: 1, color: "#3b82f6" },
                        ],
                    },
                    borderRadius: [6, 6, 0, 0],
                },
                data: [14, 18, 21, 20, 17, 22, 24, 19, 23, 26, 21, 28],
            },
        ],
    },

    statusPasienIndividu: {
        ...donutBase,
        series: [
            {
                type: "pie",
                radius: ["45%", "68%"],
                center: ["50%", "45%"],
                label: {
                    show: true,
                    formatter: "{b}\n{d}%",
                    fontSize: 11,
                    color: labelColor,
                },
                labelLine: { show: true, length: 10, length2: 8 },
                data: [
                    {
                        value: 187,
                        name: "Sudah CKG",
                        itemStyle: { color: "#22c55e" },
                    },
                    {
                        value: 61,
                        name: "Belum CKG",
                        itemStyle: { color: "#f97316" },
                    },
                    {
                        value: 14,
                        name: "Dalam Proses",
                        itemStyle: { color: "#eab308" },
                    },
                ],
            },
        ],
    },

    desaPasienIndividu: {
        backgroundColor: "transparent",
        tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
        grid: { left: 90, right: 30, top: 10, bottom: 30 },
        xAxis: {
            type: "value",
            axisLabel: { color: labelColor, fontSize: 10 },
            splitLine: { lineStyle: { color: lineColor, type: "dashed" } },
        },
        yAxis: {
            type: "category",
            data: [
                "Ngreco",
                "Kasihan",
                "Gemaharjo",
                "Tegalombo",
                "Pulosari",
                "Krisikan",
                "Kanigoro",
                "Baruharjo",
            ],
            axisLabel: { color: labelColor, fontSize: 11 },
            axisLine: { lineStyle: { color: lineColor } },
        },
        series: [
            {
                name: "Pasien",
                type: "bar",
                barMaxWidth: 24,
                itemStyle: {
                    color: "#14b8a6",
                    borderRadius: [0, 6, 6, 0],
                },
                label: {
                    show: true,
                    position: "right",
                    color: labelColor,
                    fontSize: 10,
                },
                data: [9, 12, 18, 21, 24, 28, 31, 36],
            },
        ],
    },
};

window._initChart = function (el, key) {
    if (!el || !window._chartOpts?.[key]) {
        return;
    }

    const existing = echarts.getInstanceByDom(el);
    if (existing) {
        existing.dispose();
    }

    const chart = echarts.init(el, null, { renderer: "canvas" });
    chart.setOption(window._chartOpts[key]);

    const resizeObserver = new ResizeObserver(() => chart.resize());
    resizeObserver.observe(el);
};
