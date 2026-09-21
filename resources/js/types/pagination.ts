export type PageMeta = {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

export const defaultPageSize = 10

export const emptyPageMeta: PageMeta = {
  currentPage: 1,
  lastPage: 1,
  perPage: defaultPageSize,
  total: 0,
}
