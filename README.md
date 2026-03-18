# magnum-sdvn-switch-port-audit-scrip

## Link data device mapping script

Run:

```bash
php link_data_device_mapping.php <link_status.csv> <link_sdvn.csv> [output.csv]
```

- `link_status.csv` columns:
  1. `link_name`
  2. `link_state`
- `link_sdvn.csv` columns:
  1. `link_name`
  2. `start_port`
  3. `start_port_capacity`
  4. `end_port`
  5. `end_port_capacity`

Output CSV columns:
1. `device_naame`
2. `enet_number`
3. `link_capacity`
4. `link_state`

Behavior notes:
- keeps each observed `enet_number` from the SDVN file unchanged
- calculates a per-device ENET bucket (`1, 32, 64, 128, 256, 512, 1024`) from the largest observed ENET value
- adds rows for missing ENET numbers from `1..bucket` for each device (with blank `link_capacity` and `link_state`)
- sorts output by `device_naame` (column 1), then `enet_number` (column 2)
