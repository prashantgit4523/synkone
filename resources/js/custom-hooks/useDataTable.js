import React, {useEffect} from 'react';
import {useDispatch, useSelector} from "react-redux";
import {addTable, refreshTable, updateRow, updateTable} from "../store/slices/dataTableSlice";

const useDataTable = (tagBefore) => {
    const dataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const tag = tagBefore + '-' + dataScope;

    const loading = useSelector(state => state.datatable[tag]?.loading);
    const selectedRowIds = useSelector(state => state.datatable[tag]?.selectedRowIds);
    const rows = useSelector(state => state.datatable[tag]?.rows);
    const q = useSelector(state => state.datatable[tag]?.q);
    const dispatch = useDispatch();

    useEffect(() => {
        dispatch(addTable({tag}));
    }, [])

    const data = Object.values(rows ?? {});

    return {
        ready: typeof loading !== "undefined",
        tag,
        loading,
        updateRow: (id, row, callback) => {
            dispatch(updateRow({
                tag,
                id,
                row,
            }));
            typeof callback === 'function' && callback();
        },
        refresh: () => {
            dispatch(refreshTable({tag}));
        },
        data,
        selectedRows: data.filter(r => selectedRowIds.includes(r.id)),
        q,
        resetTable: () => {
            dispatch(updateTable({
                tag,
                currentPage: 1,
                q: '',
                sortType: 'asc',
                selectedColumn: null
            }));
        },
    }
}

export default useDataTable;