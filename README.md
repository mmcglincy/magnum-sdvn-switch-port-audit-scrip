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
3. `physical_port`
4. `link_capacity`
5. `link_state`
6. `physical_port_used`

Behavior notes:
- keeps each observed `enet_number` from the SDVN file unchanged
- calculates a per-device ENET bucket (`1, 32, 64, 128, 256, 512, 1024`) from the largest observed ENET value
- adds rows for missing ENET numbers from `1..bucket` for each device (with blank `link_capacity` and `link_state`)
- calculates `physical_port` as groups of 8 ENETs (`1-8 => 1`, `9-16 => 2`, ...)
- marks `physical_port_used` as `true` for all rows in a physical port if any row in that physical port has a non-blank `link_capacity`; otherwise `false`
- if any `link_capacity` equals `100`, marks the next three ENET rows for that device as `USED` when blank
- if any `link_capacity` equals `100`, marks any remaining blank `link_capacity` rows in that same physical port as `OPEN`
- if any `link_capacity` equals `25` or `10`, marks blank `link_capacity` rows in that same physical port as `OPEN`
- if a physical port is marked `physical_port_used=false`, any blank `link_capacity` rows in that physical port are marked as `OPEN`
- sorts output by `device_naame` (column 1), then `enet_number` (column 2)
