import React, { Fragment, useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import ReactPagination from "../../../../common/react-pagination/ReactPagination";
import { fetchCampaignUserList } from "../../../../store/actions/policy-management/campaigns";
import { useDidMountEffect } from "../../../../custom-hooks";
import { Inertia } from "@inertiajs/inertia";
import moment from "moment/moment";
import ReactTooltip from "react-tooltip";
import SortAscendingIcon from "../../../../common/custom-datatable/components/SortAscendingIcon";
import SortDescendingIcon from "../../../../common/custom-datatable/components/SortDescendingIcon";

import CampaignUsersActivities from "./CampaignUsersActivities";

function CampaignUsersTable(props) {
  const { campaign } = props;
  const dispatch = useDispatch();
  const appDataScope = useSelector(
    (state) => state.appDataScope.selectedDataScope.value
  );
  const [campaignUsers, setCampaignUsers] = useState([]);
  const [activeKeys, setActiveKeys] = useState([]);
  const [filterByUserName, setFilterByUserName] = useState("");
  const [pageLengthFilter, setPageLengthFilter] = useState(10);
  const [usersTablePagination, setUsersTablePagination] = useState({});
  const [usersTableCurrentPage, setUsersTableCurrentPage] = useState(1);
  const [sendingMailRemainder, setSendingMailRemainder] = useState(false);

  // sorting
  const [selectedColumn, setSelectedColumn] = useState(null);
  const [sortType, setSortType] = useState('asc');

  /* Trigger on change search and pageLengthFilter change */
  useDidMountEffect(() => {
    loadCampaignUsers(usersTableCurrentPage);
  }, [filterByUserName, usersTableCurrentPage, selectedColumn, sortType]);

  useEffect(() => {
    loadCampaignUsers();
  }, [pageLengthFilter]);

  const loadCampaignUsers = async (currentPage = null) => {
    const params = {
      filter_by_user_name: filterByUserName,
      page_length: pageLengthFilter,
      data_scope: appDataScope,
      sort_type: sortType,
      sort_by: selectedColumn
    };

    if (currentPage) {
      params["page"] = currentPage;
    }

    let {
      payload: { campaignUsers },
    } = await dispatch(
      fetchCampaignUserList({
        campaignId: campaign.id,
        params: params,
      })
    );

    setCampaignUsers(campaignUsers.data);
    setUsersTablePagination({
      links: campaignUsers.links,
      per_page: campaignUsers.per_page,
      total: campaignUsers.total,
    });
  };

  const toggleActiveKeys = (key) => {
    let prevActiveKeys = [...activeKeys];

    /* Toggling the value in array*/
    let updatedActiveKeys = _.xor(prevActiveKeys, [key]);

    /* updating the active key state */
    setActiveKeys(updatedActiveKeys);
  };

  const renderUserListSection = (campaignUser) => {
    return (
      <Fragment key={campaignUser.id}>
        <tr>
          <td>
            <span className="icon-sec me-2 expandable-icon-wp cursor-pointer">
              <a
                onClick={() => toggleActiveKeys(campaignUser.id)}
                aria-expanded="false"
                aria-controls="collapseExample"
              >
                <i
                  className={`icon fas expand-icon-w fa-chevron-${
                    activeKeys.includes(campaignUser.id) ? "down" : "right"
                  } me-2`}
                ></i>
              </a>
            </span>
          </td>
          <td>{campaignUser.first_name}</td>
          <td className="hide-on-xs hide-on-sm">{campaignUser.last_name}</td>
          <td className="hide-on-xs">{campaignUser.email}</td>
          <td className="hide-on-xs">{campaignUser.department}</td>
          <td className="hide-on-xs hide-on-sm">
            {campaign.campaign_type == 'awareness-campaign' && <>
              <span
                className="badge"
                style={{
                  background: campaignUser.user_awareness_completion_status["color"],
                }}
              >
                {campaignUser.user_awareness_completion_status["status"]}
              </span>
            </>}
            {campaign.campaign_type != 'awareness-campaign' && <>
              <span
                className="badge bg-info text-white"
                style={{
                  background: campaignUser.user_acknowledgement_status["color"],
                }}
              >
                {campaignUser.user_acknowledgement_status["status"]}
              </span>
            </>}
          </td>
        </tr>
        
        <CampaignUsersActivities campaignUser={campaignUser} activeKeys={activeKeys} campaign={campaign} />
        
      </Fragment>
    );
  };

  const sendRemainderEmail = () => {
    let URL = route(
      "policy-management.campaigns.send-users-reminder",
      campaign.id
    );
    setSendingMailRemainder(true);

    Inertia.post(URL, null, {
      onFinish: () => {
        setSendingMailRemainder(false);
      }
    });
  };

  const handleSelectedColumn = (column) => () => {
    if(column === selectedColumn){
      return setSortType(sortType === 'asc' ? 'desc' : 'asc');
    }
    setSelectedColumn(column);
  };

  const Th = ({children, column}) => {
    return (
        <th role="button" onClick={handleSelectedColumn(column)}>
          {children} {column === selectedColumn ? <span>{sortType === 'asc' ? <SortAscendingIcon/> : <SortDescendingIcon/>}</span> : null}
        </th>
    );
  }

  return (
    <Fragment>
      <div className="card">
        <div className="card-body table-container">
          <div className="mb-3 clearfix">
            <h3 className="mb-4">Details</h3>
            {/* chage length of data */}
            <div className="row">
              <div className="col-md-2">
                <div className="custom-limit">
                  <label>
                    <span>Show</span>
                    <select
                      name="user_list_length"
                      onChange={(event) => {
                        setPageLengthFilter(event.target.value);
                      }}
                      className="form-select form-select-sm cursor-pointer form-control form-control-sm"
                    >
                      <option value={10}>10</option>
                      <option value={25}>25</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </select>
                    <span>Entries</span>
                  </label>
                </div>
              </div>
              <div className="col-md-10 search-wrapper">
                <div className="float-end form-left-mobile">
                  <div className="row align-items-center">
                    <div className="col-12">
                      <input
                        type="text"
                        name="filter_by_user_name"
                        onChange={(e) => {
                          setFilterByUserName(e.target.value);
                        }}
                        className="form-control form-control-sm"
                        placeholder="Search..."
                      />
                    </div>
                  </div>
                </div>
                <span
                  data-tip={(campaign.status == "archived") ? 'Campaign is completed' : (moment().isBefore(campaign.launch_date) ? 'Campaign hasn\'t started' : '' )}
                  className="float-end search-wrapper-btn"
                  style={(campaign.status == "archived" || moment().isBefore(campaign.launch_date)) ? { cursor: 'not-allowed', } : {}}
                >
                  <button
                    className="btn btn-sm btn-primary waves-effect waves-light float-end me-2"
                    onClick={() => { sendRemainderEmail(); }}
                    disabled={(campaign.status == "archived" || moment().isBefore(campaign.launch_date)) ? 'disabled' : sendingMailRemainder}
                  >
                    Send Reminder
                  </button>
                </span>
                <ReactTooltip />
              </div>
            </div>{" "}
            {/* End of row */}
          </div>
          <table className="table table-centered display table-hover w-100">
            <thead>
              <tr>
                <th></th>
                <Th column="first_name">First name</Th>
                <Th column="last_name">Last name</Th>
                <Th column="email">Email</Th>
                <Th column="department">Department</Th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="campaign-users-wp">
              {campaignUsers.map((campaignUser) => {
                {
                  /* first risk item */
                }
                return renderUserListSection(campaignUser);
              })}

              {/* pagination */}
              <tr>
                <td colSpan="6">
                  <div className="float-end campaign-users-pagination">
                    <ReactPagination
                      itemsCountPerPage={pageLengthFilter}
                      totalItemsCount={usersTablePagination.total}
                      onChange={(page) => {
                        setUsersTableCurrentPage(page);
                      }}
                    ></ReactPagination>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      {/* End of table-container */}
    </Fragment>
  );
}

export default CampaignUsersTable;
