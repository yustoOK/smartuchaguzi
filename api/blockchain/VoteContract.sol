// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract VoteContract {
    address public admin;
    
    // Mapping to track if a voter has voted for a position in an election
    mapping(address => mapping(uint256 => mapping(uint256 => bool))) public hasVoted;
    
    // Mapping to store vote counts for each candidate per position
    mapping(uint256 => mapping(uint256 => uint256)) public voteCount;
    
    // Array to store all votes
    Vote[] public votes;

    // Struct to represent a vote
    struct Vote {
        uint256 electionId;
        address voter;
        uint256 positionId;
        uint256 candidateId;
        uint256 timestamp;
    }

    // Struct to hold election time data (fetched off-chain)
    struct ElectionTime {
        uint256 startDate;
        uint256 endDate;
    }

    // Mapping to store election start and end times (set by admin via off-chain data)
    mapping(uint256 => ElectionTime) public electionTimes;

    // Events for logging
    event VoteCast(uint256 electionId, address indexed voter, uint256 positionId, uint256 candidateId);
    event ElectionTimeSet(uint256 electionId, uint256 startDate, uint256 endDate);

    // Modifiers
    modifier onlyAdmin() {
        require(msg.sender == admin, "Not authorized");
        _;
    }

    modifier onlyDuringElection(uint256 electionId) {
        ElectionTime memory electionTime = electionTimes[electionId];
        require(electionTime.startDate > 0 && electionTime.endDate > 0, "Election time not set");
        require(block.timestamp >= electionTime.startDate, "Election not yet started");
        require(block.timestamp <= electionTime.endDate, "Election has ended");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    // Function to set election start and end times (fetched off-chain from the database)
    function setElectionTime(
        uint256 electionId,
        uint256 startDate,
        uint256 endDate
    ) external onlyAdmin {
        require(startDate < endDate, "Invalid time range");
        electionTimes[electionId] = ElectionTime(startDate, endDate);
        emit ElectionTimeSet(electionId, startDate, endDate);
    }

    // Function to cast a vote
    function castVote(
        uint256 electionId,
        uint256 positionId,
        uint256 candidateId
    ) external onlyDuringElection(electionId) {
        // Ensure voter hasn't voted for this position in this election
        require(!hasVoted[msg.sender][electionId][positionId], "Already voted for this position");
        
        // Ensure position ID is within the limit (1 to 5)
        require(positionId >= 1 && positionId <= 5, "Invalid position ID (must be 1 to 5)");

        // Validate candidate (off-chain check assumed; replace with actual logic if needed)
        bool isValidCandidate = true; // Placeholder for off-chain validation
        require(isValidCandidate, "Invalid candidate");

        // Record the vote
        hasVoted[msg.sender][electionId][positionId] = true;
        voteCount[positionId][candidateId]++;
        votes.push(Vote(electionId, msg.sender, positionId, candidateId, block.timestamp));
        emit VoteCast(electionId, msg.sender, positionId, candidateId);
    }

    // Function to get the vote count for a candidate in a position
    function getVoteCount(uint256 positionId, uint256 candidateId) external view returns (uint256) {
        return voteCount[positionId][candidateId];
    }

    // Function to retrieve all votes for a given election
    function getVotesByElection(uint256 electionId) external view returns (Vote[] memory) {
        uint256 count = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                count++;
            }
        }

        Vote[] memory result = new Vote[](count);
        uint256 index = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                result[index] = votes[i];
                index++;
            }
        }
        return result;
    }
}