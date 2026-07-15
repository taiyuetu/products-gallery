<?php
// Column definitions – single source of truth for CSV import, DB fields, forms, filters and export.
//
// Keys:
//   field      – database column name
//   label      – Chinese label (must match CSV header exactly)
//   type       – 'text' | 'number'  (controls form input type)
//   filterable – appear in the search/filter panel
//   list       – appear as a column in the products index table
//
// To add a new column: add an entry here + add the column to setup.sql + run ALTER TABLE.

return [
    // ── 基本信息 ──────────────────────────────────────────────────────────────
    ['field' => 'name',              'label' => '名称',              'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '基本信息'],
    ['field' => 'tqb_code',          'label' => 'TQB编码',           'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '基本信息'],
    ['field' => 'oem_number',        'label' => 'OEM号码',           'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '基本信息'],
    ['field' => 'production_code',   'label' => '生产码',            'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '基本信息'],
    ['field' => 'no_stock_purchase', 'label' => '无库存需采购',      'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '基本信息'],

    // ── 车型信息 ──────────────────────────────────────────────────────────────
    ['field' => 'car_series',        'label' => '车系',              'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '车型信息'],
    ['field' => 'car_model',         'label' => '车型',              'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '车型信息'],
    ['field' => 'universal_model',   'label' => '通用车型',          'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '车型信息'],
    ['field' => 'trade_car_series',  'label' => '外贸车系',          'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '车型信息'],
    ['field' => 'trade_car_model',   'label' => '外贸车型',          'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '车型信息'],
    ['field' => 'trade_universal',   'label' => '外贸通用车型',      'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '车型信息'],

    // ── 品牌编号 ──────────────────────────────────────────────────────────────
    ['field' => 'bca',               'label' => 'BCA',               'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'skf',               'label' => 'SKF',               'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'snr',               'label' => 'SNR',               'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'timken',            'label' => 'TIMKEN',            'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'nsk',               'label' => 'NSK',               'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'ntn',               'label' => 'NTN',               'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],
    ['field' => 'koyo',              'label' => 'KOYO',              'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '品牌编号'],

    // ── 规格参数 ──────────────────────────────────────────────────────────────
    ['field' => 'cost',              'label' => '成本',              'type' => 'number', 'filterable' => false, 'list' => true,  'tab' => '规格参数'],
    ['field' => 'spline_teeth',      'label' => '花键/磁极/齿圈齿数', 'type' => 'text',  'filterable' => false, 'list' => false, 'tab' => '规格参数'],
    ['field' => 'dimensions',        'label' => '尺寸',              'type' => 'text',   'filterable' => false, 'list' => true,  'tab' => '规格参数'],
    ['field' => 'weight',            'label' => '重量',              'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '规格参数'],
    ['field' => 'inner_box_size',    'label' => '内盒尺寸',          'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '规格参数'],

    // ── 库存状态 ──────────────────────────────────────────────────────────────
    ['field' => 'original_category', 'label' => '原表分类',          'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '库存状态'],
    ['field' => 'stock_status',      'label' => '库存状态',          'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '库存状态'],
    ['field' => 'in_system',         'label' => '是否已录入系统',    'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '库存状态'],
    ['field' => 'system_code',       'label' => '系统关联编码',      'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '库存状态'],
    ['field' => 'warehouse_a',       'label' => 'A仓可出判断',       'type' => 'text',   'filterable' => true,  'list' => true,  'tab' => '库存状态'],

    // ── 库存 ──────────────────────────────────────────────────────────────────
    ['field' => 'stock_qty',         'label' => '库存数量',          'type' => 'number', 'filterable' => false, 'list' => true,  'tab' => '库存'],
    ['field' => 'stock_max',         'label' => '库存上限',          'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '库存'],
    ['field' => 'stock_min',         'label' => '库存下限',          'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '库存'],

    // ── 供应商 ────────────────────────────────────────────────────────────────
    ['field' => 'supplier1',         'label' => '首选供应商',        'type' => 'text',   'filterable' => true,  'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier1_price',   'label' => '首选采购价',        'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier2',         'label' => '备用供应商1',       'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier2_price',   'label' => '备用采购价1',       'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier3',         'label' => '备用供应商2',       'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier3_price',   'label' => '备用采购价2',       'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier4',         'label' => '备用供应商3',       'type' => 'text',   'filterable' => false, 'list' => false, 'tab' => '供应商'],
    ['field' => 'supplier4_price',   'label' => '备用采购价3',       'type' => 'number', 'filterable' => false, 'list' => false, 'tab' => '供应商'],
];
