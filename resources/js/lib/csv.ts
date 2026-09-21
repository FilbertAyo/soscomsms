export function parseCsvText(text: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false
  const input = text.replace(/^\uFEFF/, '')

  for (let index = 0; index < input.length; index += 1) {
    const character = input[index]
    const next = input[index + 1]

    if (quoted) {
      if (character === '"' && next === '"') {
        cell += '"'
        index += 1
        continue
      }
      if (character === '"') {
        quoted = false
        continue
      }
      cell += character
      continue
    }

    if (character === '"') {
      quoted = true
      continue
    }

    if (character === ',') {
      row.push(cell)
      cell = ''
      continue
    }

    if (character === '\n') {
      row.push(cell)
      rows.push(row)
      row = []
      cell = ''
      continue
    }

    if (character === '\r') {
      continue
    }

    cell += character
  }

  if (cell !== '' || row.length > 0) {
    row.push(cell)
    rows.push(row)
  }

  return rows.filter((entry) => entry.some((value) => value.trim() !== ''))
}
