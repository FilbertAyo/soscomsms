export type MessageTemplate = {
  id: number
  name: string
  body: string
  encoding: 'gsm' | 'unicode'
  characterCount: number
  segmentCount: number
}
