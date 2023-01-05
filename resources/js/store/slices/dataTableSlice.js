import {createSlice} from "@reduxjs/toolkit";

const initialState = {};

const dataTableSlice = createSlice({
    name: 'datatable',
    initialState,
    reducers: {
        addTable: (state, action) => {
            const {tag} = action.payload;
            if (!Object.keys(state).includes(tag)) {
                state[tag] = {
                    selectAll: false,
                    rowIds: [],
                    loading: true,
                    selectedRowIds: [],
                    selectedColumn: null,
                    sortType: 'asc',
                    rows: {},
                    currentPage: 1,
                    perPage: 10,
                    totalPages: 1,
                    stats: {
                        currentPageTotal: 0,
                        totalRecords: 0
                    },
                    q: '',
                    refreshToken: (Math.random() + 1).toString(36).substring(7),
                    toggledRowIndex: null
                }
            }
        },
        updateTable: (state, action) => {
            const {tag, ...rest} = action.payload;
            if (Object.keys(state).includes(tag)) {
                state[tag] = {
                    ...state[tag],
                    ...rest
                }
            }
        },
        deleteTable: (state, action) => {
            const {tag} = action.payload;
            delete state[tag];
        },
        updateRow: (state, action) => {
            const {tag, id, row} = action.payload;
            state[tag].rows[id] = {...state[tag].rows[id], ...row};
        },
        toggleRowSelection: (state, action) => {
            const {tag, id} = action.payload;
            if (state[tag].selectedRowIds.includes(id)) {
                state[tag].selectedRowIds = state[tag].selectedRowIds.filter(i => i !== id);
            } else {
                state[tag].selectedRowIds.push(id);
            }
            state[tag].selectAll = state[tag].selectedRowIds.length === Object.keys(state[tag].rows).length;
        },
        refreshTable: (state, action) => {
            const {tag} = action.payload;
            state[tag].refreshToken = (Math.random() + 1).toString(36).substring(7);
        },
        toggleRow: (state, action) => {
            const {tag, index} = action.payload;
            if(state[tag].toggledRowIndex === index){
                state[tag].toggledRowIndex = null;
                return;
            }
            state[tag].toggledRowIndex = index;
        },
        toggleSorting: (state, action) => {
            const {tag, accessor} = action.payload;
            const {sortType, selectedColumn} = state[tag];

            const newSortType = sortType === 'asc' ? 'desc' : 'asc';
            state[tag].selectedColumn = accessor;
            state[tag].sortType = (accessor === selectedColumn) ? newSortType : sortType
        },
        setRows: (state, action) => {
            const {tag, rows} = action.payload;
            state[tag].rows = rows;
        }
        // updateTableStats : (state, action) => {
        //     const {tag, currentPageTotal, totalRecords} = action.payload;
        //     state[tag].stats = {currentPageTotal, totalRecords};
        // }
    }
});

export const {
    addTable,
    updateTable,
    deleteTable,
    updateRow,
    toggleRowSelection,
    refreshTable,
    toggleRow,
    toggleSorting,
    setRows
} = dataTableSlice.actions;
export default dataTableSlice.reducer;