export const storePerPageData = (data) => {
    return {
        type: 'store_per_page_data',
        payload: data
    }
}

export const storeCurrentPageData = (data) => {
    return {
        type: 'store_current_page_data',
        payload: data
    }
} 