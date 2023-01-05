import React, {useEffect} from 'react';

import ContentLoader from "../content-loader/ContentLoader";
import useDataTable from "../../custom-hooks/useDataTable";
import Table from "./components/Table";

import {useDispatch} from "react-redux";
import {deleteTable} from "../../store/slices/dataTableSlice";

import './styles.scss';

const AppDataTable = ({
                          tag,
                          columns,
                          fetchUrl,
                          rowIdentifier = 'id',
                          variant = 'primary',
                          search = false,
                          selectable = false,
                          disableSelect = null,
                          resetOnExit = false,
                          dateToFilter = null,
                          refresh,
                          data: params = {},
                          onPageChange = null,
                          offlineMode = false,
                          rows: offlineRows = [],
                          emptyString = "Sorry, your search did not return any results. Please try again."
                      }) => {
    const {ready, loading, tag: newTag} = useDataTable(tag);
    const dispatch = useDispatch();

    useEffect(() => {
        return () => resetOnExit ? dispatch(deleteTable({tag: newTag})) : false;
    }, [])

    return (
        <ContentLoader show={loading}>
            {ready ? <
                Table
                tag={newTag}
                columns={columns}
                fetchUrl={fetchUrl}
                rowIdentifier={rowIdentifier}
                variant={variant}
                search={search}
                selectable={selectable}
                disableSelect={disableSelect}
                dateToFilter={dateToFilter}
                refresh={refresh}
                params={params}
                onPageChange={onPageChange}
                offlineMode={offlineMode}
                offlineRows={offlineRows}
                emptyString={emptyString}
            /> : null}
        </ContentLoader>
    );
}

export default AppDataTable;